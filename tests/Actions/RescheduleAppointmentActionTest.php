<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\Actions\RescheduleAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Events\AppointmentRescheduled;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

beforeEach(function (): void {
    $this->action = app(RescheduleAppointmentAction::class);
});

it('moves an appointment and recomputes its end time', function (): void {
    Event::fake();
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00'),
        'duration_minutes' => 60,
    ]);

    $this->action->execute($appointment, CarbonImmutable::parse('2026-07-02 14:00'));

    expect($appointment->fresh())
        ->starts_at->format('Y-m-d H:i')->toBe('2026-07-02 14:00')
        ->ends_at->format('Y-m-d H:i')->toBe('2026-07-02 15:00');

    Event::assertDispatched(
        AppointmentRescheduled::class,
        fn (AppointmentRescheduled $e) => $e->previousStartsAt->format('Y-m-d H:i') === '2026-07-01 09:00',
    );
});

it('can change the duration while rescheduling', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00'),
        'duration_minutes' => 60,
    ]);

    $this->action->execute($appointment, CarbonImmutable::parse('2026-07-01 11:00'), durationMinutes: 30);

    expect($appointment->fresh())
        ->duration_minutes->toBe(30)
        ->ends_at->format('H:i')->toBe('11:30');
});

it('ignores the appointment itself when checking conflicts on reschedule', function (): void {
    $user = User::create();
    $appointment = app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'Solo',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $result = $this->action->execute(
        $appointment,
        CarbonImmutable::parse('2026-07-01 09:30'),
        preventConflicts: true,
    );

    expect($result->starts_at->format('H:i'))->toBe('09:30');
});

it('skips participants whose related model no longer exists', function (): void {
    $user = User::create();
    $appointment = app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'Orphaned',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $user->forceDelete();
    $appointment->load('participants');

    $result = $this->action->execute(
        $appointment,
        CarbonImmutable::parse('2026-07-02 09:00'),
        preventConflicts: true,
    );

    expect($result->starts_at->format('Y-m-d H:i'))->toBe('2026-07-02 09:00');
});

it('throws when rescheduling into another appointment for the same participant', function (): void {
    $user = User::create();

    $first = app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'First',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $second = app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'Second',
        startsAt: CarbonImmutable::parse('2026-07-01 13:00'),
        durationMinutes: 60,
        participants: [new ParticipantData($user)],
    ));

    $this->action->execute($second, CarbonImmutable::parse('2026-07-01 09:15'), preventConflicts: true);

    expect($first)->toBeInstanceOf(Appointment::class);
})->throws(SchedulingConflictException::class);
