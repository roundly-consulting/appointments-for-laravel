<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

beforeEach(function (): void {
    $this->action = app(CreateAppointmentAction::class);
});

it('creates an appointment from a DTO and derives ends_at', function (): void {
    $appointment = $this->action->execute(new AppointmentData(
        name: 'Kickoff',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 90,
        description: 'Planning',
        meta: ['location' => 'HQ'],
    ));

    expect($appointment)
        ->toBeInstanceOf(Appointment::class)
        ->name->toBe('Kickoff')
        ->description->toBe('Planning')
        ->duration_minutes->toBe(90)
        ->status->toBe(Status::Pending)
        ->and($appointment->ends_at->format('Y-m-d H:i'))->toBe('2026-07-01 10:30')
        ->and($appointment->meta->toArray())->toBe(['location' => 'HQ']);
});

it('attaches participants with role and meta', function (): void {
    $host = User::create();
    $guest = User::create();

    $appointment = $this->action->execute(new AppointmentData(
        name: 'Kickoff',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        participants: [
            new ParticipantData($host, ParticipantRole::Organiser, ['is_host' => true]),
            new ParticipantData($guest),
        ],
    ));

    expect($appointment->participants)->toHaveCount(2)
        ->and($appointment->participants->first()->role)->toBe(ParticipantRole::Organiser)
        ->and($appointment->participants->first()->meta->toArray())->toBe(['is_host' => true]);
});

it('applies the appointment timezone to starts_at', function (): void {
    $appointment = $this->action->execute(new AppointmentData(
        name: 'Kickoff',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        timezone: 'Europe/Bratislava',
    ));

    expect($appointment->timezone)->toBe('Europe/Bratislava')
        ->and($appointment->startsAtLocal()->format('Y-m-d H:i'))->toBe('2026-07-01 11:00');
});

it('throws when a participant conflicts and prevention is enabled', function (): void {
    $user = User::create();

    $this->action->execute(new AppointmentData(
        name: 'First',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $this->action->execute(new AppointmentData(
        name: 'Clash',
        startsAt: CarbonImmutable::parse('2026-07-01 09:30'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
        preventConflicts: true,
    ));
})->throws(SchedulingConflictException::class);

it('allows a non-overlapping booking when prevention is enabled', function (): void {
    $user = User::create();

    $this->action->execute(new AppointmentData(
        name: 'First',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $second = $this->action->execute(new AppointmentData(
        name: 'Later',
        startsAt: CarbonImmutable::parse('2026-07-01 10:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
        preventConflicts: true,
    ));

    expect($second)->toBeInstanceOf(Appointment::class);
});

it('respects the global prevent_conflicts config flag', function (): void {
    config()->set('appointments.prevent_conflicts', true);
    $user = User::create();

    $this->action->execute(new AppointmentData(
        name: 'First',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $this->action->execute(new AppointmentData(
        name: 'Clash',
        startsAt: CarbonImmutable::parse('2026-07-01 09:15'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));
})->throws(SchedulingConflictException::class);
