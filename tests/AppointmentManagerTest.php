<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;

it('creates an appointment from a DTO through the facade', function (): void {
    $appointment = Appointments::create(new AppointmentData(
        name: 'Sync',
        startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        durationMinutes: 30,
    ));

    expect($appointment)->toBeInstanceOf(Appointment::class)->name->toBe('Sync');
});

it('reschedules through the facade', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00'),
        'duration_minutes' => 60,
    ]);

    Appointments::reschedule($appointment, CarbonImmutable::parse('2026-07-02 10:00'));

    expect($appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-07-02 10:00');
});

it('transitions through the facade', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    Appointments::transition($appointment, Status::Confirmed);

    expect($appointment->fresh()->status)->toBe(Status::Confirmed);
});
