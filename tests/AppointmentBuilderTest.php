<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;

it('builds an appointment fluently', function (): void {
    $host = User::create();
    $guest = User::create();

    $appointment = Appointments::schedule('Project kickoff')
        ->startingAt('2026-07-01 17:30', timezone: 'Europe/Bratislava')
        ->lasting(90)
        ->describedAs('Wings')
        ->withMeta(['location' => 'My house'])
        ->withStatus(Status::Confirmed)
        ->withParticipant($host, ParticipantRole::Organiser, ['is_host' => true])
        ->withParticipant($guest)
        ->create();

    expect($appointment)
        ->toBeInstanceOf(Appointment::class)
        ->name->toBe('Project kickoff')
        ->description->toBe('Wings')
        ->duration_minutes->toBe(90)
        ->timezone->toBe('Europe/Bratislava')
        ->status->toBe(Status::Confirmed)
        ->and($appointment->participants)->toHaveCount(2)
        ->and($appointment->participants->first()->role)->toBe(ParticipantRole::Organiser)
        ->and($appointment->startsAtLocal()->format('Y-m-d H:i'))->toBe('2026-07-01 17:30');
});

it('derives the duration from an until time', function (): void {
    $appointment = Appointments::schedule('Workshop')
        ->startingAt('2026-07-01 09:00')
        ->until('2026-07-01 11:30')
        ->create();

    expect($appointment->duration_minutes)->toBe(150);
});

it('defaults the start to now when none is given', function (): void {
    $appointment = Appointments::schedule('Ad hoc')->create();

    expect($appointment->starts_at)->not->toBeNull();
});

it('opts into conflict prevention per call', function (): void {
    $user = User::create();

    Appointments::schedule('First')
        ->startingAt('2026-07-01 09:00')
        ->lasting(60)
        ->withParticipant($user)
        ->create();

    Appointments::schedule('Clash')
        ->startingAt('2026-07-01 09:30')
        ->lasting(60)
        ->withParticipant($user)
        ->preventConflicts()
        ->create();
})->throws(SchedulingConflictException::class);

it('creates a single appointment from createRecurring without a rule', function (): void {
    $appointments = Appointments::schedule('One off')
        ->startingAt('2026-07-01 09:00')
        ->createRecurring();

    expect($appointments)->toHaveCount(1);
});

it('creates recurring appointments when a rule is set', function (): void {
    $appointments = Appointments::schedule('Weekly standup')
        ->startingAt('2026-07-06 09:00')
        ->lasting(30)
        ->recurring(new RecurrenceData(Frequency::Weekly, count: 3))
        ->createRecurring();

    expect($appointments)->toHaveCount(3)
        ->and($appointments->pluck('recurrence_group')->unique())->toHaveCount(1);
});

it('sets the venue string on its own', function (): void {
    $appointment = Appointments::schedule('Venue only')
        ->startingAt('2026-07-01 09:00')
        ->venue('Meeting Room 3')
        ->create();

    expect($appointment->location)->toBe('Meeting Room 3')
        ->and($appointment->coordinates)->toBeNull();
});

it('opens a flat approval built from standalone rule and quorum setters', function (): void {
    $a = User::create();
    $b = User::create();

    $appointment = Appointments::schedule('Config booking')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom([$a, $b])
        ->approvalRule(ApprovalRule::Quorum)
        ->approvalQuorum(1)
        ->rejectOnStageRejection(false)
        ->create();

    $request = $appointment->approvalRequests()->latest('id')->first();

    expect($request?->rule)->toBe(ApprovalRule::Quorum)
        ->and($request?->quorum)->toBe(1);
});
