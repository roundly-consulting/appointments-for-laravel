<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentStatusChanged;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Approvals\Facades\Approvals;

it('never moves a cancelled appointment when its open approval is decided later', function (string $decision, Status $final): void {
    // The default: enforce_transitions off.
    $organiser = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    $appointment->transitionTo($final);

    $changes = [];
    Event::listen(AppointmentStatusChanged::class, function (AppointmentStatusChanged $event) use (&$changes): void {
        $changes[] = [$event->from, $event->to];
    });

    Approvals::for($appointment)->as($organiser)->{$decision}();

    expect($appointment->fresh()->status)->toBe($final)
        ->and($changes)->toBe([]);
})->with([
    'approved after cancel' => ['approve', Status::Cancelled],
    'rejected after cancel' => ['reject', Status::Cancelled],
    'approved after decline' => ['approve', Status::Declined],
]);

it('still forces a mapped move between live statuses when not enforcing', function (): void {
    $organiser = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    $appointment->confirm();

    Approvals::for($appointment)->as($organiser)->reject();

    // confirmed → declined is outside the matrix, but confirmed is not final.
    expect($appointment->fresh()->status)->toBe(Status::Declined);
});

it('lets only a named approver decide the booking', function (): void {
    $organiser = User::create();
    $outsider = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    expect(fn () => Approvals::for($appointment)->as($outsider)->approve())
        ->toThrow(UnauthorizedApprovalException::class);

    expect($appointment->fresh()->status)->toBe(Status::Pending);

    Approvals::for($appointment)->as($organiser)->approve();

    expect($appointment->fresh()->status)->toBe(Status::Confirmed);
});

it('lets only the stage approvers decide a staged booking', function (): void {
    $reception = User::create();
    $clinician = User::create();
    $outsider = User::create();

    $appointment = Appointments::schedule('Two-desk booking')
        ->startingAt('2026-07-01 09:00')
        ->approvalStages([new StageDefinition([$reception]), new StageDefinition([$clinician])])
        ->create();

    expect(fn () => Approvals::for($appointment)->as($outsider)->approve())
        ->toThrow(UnauthorizedApprovalException::class);

    Approvals::for($appointment)->as($reception)->approve();
    Approvals::for($appointment)->as($clinician)->approve();

    expect($appointment->fresh()->status)->toBe(Status::Confirmed);
});

it('lets only the preset approvers decide a workflow booking', function (): void {
    config()->set('approvals.workflows.clinic', [
        'stages' => [
            ['rule' => 'unanimous', 'required_approvers' => 1, 'name' => 'clinician'],
        ],
    ]);

    $clinician = User::create();
    $outsider = User::create();

    $appointment = Appointments::schedule('Preset booking')
        ->startingAt('2026-07-01 09:00')
        ->approvalWorkflow('clinic')
        ->approvalStageApprovers([[$clinician]])
        ->create();

    expect(fn () => Approvals::for($appointment)->as($outsider)->reject())
        ->toThrow(UnauthorizedApprovalException::class);

    expect($appointment->fresh()->status)->toBe(Status::Pending);
});
