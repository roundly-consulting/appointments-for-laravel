<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentStatusChanged;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Facades\Approvals;

it('confirms a pending appointment when its approval is approved', function (): void {
    $organiser = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    expect($appointment->status)->toBe(Status::Pending)
        ->and($appointment->isPendingApproval())->toBeTrue();

    $captured = [];
    Event::listen(AppointmentStatusChanged::class, function (AppointmentStatusChanged $e) use (&$captured): void {
        $captured[] = [$e->from, $e->to];
    });

    Approvals::for($appointment)->as($organiser)->approve();

    expect($appointment->fresh()?->status)->toBe(Status::Confirmed)
        ->and($captured)->toBe([[Status::Pending, Status::Confirmed]]);
});

it('declines a pending appointment when its approval is rejected', function (): void {
    $organiser = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    Approvals::for($appointment)->as($organiser)->reject();

    expect($appointment->fresh()?->status)->toBe(Status::Declined);
});

it('confirms once a quorum is reached', function (): void {
    $a = User::create();
    $b = User::create();

    $appointment = Appointments::schedule('Quorum booking')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom([$a, $b], ApprovalRule::Quorum, quorum: 1)
        ->create();

    Approvals::for($appointment)->as($a)->approve();

    expect($appointment->fresh()?->status)->toBe(Status::Confirmed);
});

it('stays pending until a unanimous approval completes', function (): void {
    $a = User::create();
    $b = User::create();

    $appointment = Appointments::schedule('Unanimous booking')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom([$a, $b])
        ->create();

    Approvals::for($appointment)->as($a)->approve();

    expect($appointment->fresh()?->status)->toBe(Status::Pending);

    Approvals::for($appointment)->as($b)->approve();

    expect($appointment->fresh()?->status)->toBe(Status::Confirmed);
});

it('is a no-op on a double resolution', function (): void {
    $organiser = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    Approvals::for($appointment)->as($organiser)->approve();
    Approvals::for($appointment)->as($organiser)->approve();

    expect($appointment->fresh()?->status)->toBe(Status::Confirmed);
});

it('honours the transition guard when enforcing', function (): void {
    config()->set('appointments.approvals.enforce_transitions', true);

    $organiser = User::create();

    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    $appointment->cancel();

    Approvals::for($appointment)->as($organiser)->approve();

    // Cancelled is terminal; the mapped Confirmed transition is illegal and skipped.
    expect($appointment->fresh()?->status)->toBe(Status::Cancelled);
});

it('leaves an appointment created without approval untouched', function (): void {
    $appointment = Appointments::schedule('Plain booking')
        ->startingAt('2026-07-01 09:00')
        ->create();

    expect($appointment->status)->toBe(Status::Pending)
        ->and($appointment->approvalRequests()->count())->toBe(0);
});

it('opens a flat request from a named workflow preset', function (): void {
    config()->set('approvals.workflows.booking', [
        'rule' => ApprovalRule::Quorum->value,
        'quorum' => 2,
        'required_approvers' => 3,
    ]);

    $a = User::create();
    $b = User::create();
    $c = User::create();

    $appointment = Appointments::schedule('Preset booking')
        ->startingAt('2026-07-01 09:00')
        ->approvalWorkflow('booking')
        ->requireApprovalFrom([$a, $b, $c])
        ->create();

    Approvals::for($appointment)->as($a)->approve();
    expect($appointment->fresh()?->status)->toBe(Status::Pending);

    Approvals::for($appointment)->as($b)->approve();
    expect($appointment->fresh()?->status)->toBe(Status::Confirmed);
});

it('opens a staged request from a named workflow preset', function (): void {
    config()->set('approvals.workflows.clinic', [
        'stages' => [
            ['rule' => ApprovalRule::Unanimous->value, 'required_approvers' => 1, 'name' => 'reception'],
            ['rule' => ApprovalRule::Any->value, 'required_approvers' => 1, 'name' => 'clinician'],
        ],
    ]);

    $reception = User::create();
    $clinician = User::create();

    $appointment = Appointments::schedule('Clinic preset')
        ->startingAt('2026-07-01 09:00')
        ->approvalWorkflow('clinic')
        ->approvalStageApprovers([[$reception], [$clinician]])
        ->create();

    expect($appointment->currentStage()?->name)->toBe('reception');

    Approvals::for($appointment)->as($reception)->approve();
    Approvals::for($appointment)->as($clinician)->approve();

    expect($appointment->fresh()?->status)->toBe(Status::Confirmed);
});

it('rejects a staged booking when a stage is rejected', function (): void {
    $reception = User::create();
    $clinician = User::create();

    $appointment = Appointments::schedule('Staged rejection')
        ->startingAt('2026-07-01 09:00')
        ->approvalStages([
            new StageDefinition([$reception]),
            new StageDefinition([$clinician]),
        ])
        ->rejectOnStageRejection()
        ->create();

    Approvals::for($appointment)->as($reception)->reject();

    expect($appointment->fresh()?->status)->toBe(Status::Declined);
});

it('opens a staged pipeline and confirms once every stage clears', function (): void {
    $reception = User::create();
    $clinician = User::create();

    $appointment = Appointments::schedule('Staged booking')
        ->startingAt('2026-07-01 09:00')
        ->approvalStages([
            new StageDefinition([$reception]),
            new StageDefinition([$clinician]),
        ])
        ->create();

    Approvals::for($appointment)->as($reception)->approve();
    expect($appointment->fresh()?->status)->toBe(Status::Pending);

    Approvals::for($appointment)->as($clinician)->approve();
    expect($appointment->fresh()?->status)->toBe(Status::Confirmed);
});
