<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('builds with sensible defaults', function (): void {
    $data = new AppointmentData(
        name: 'Kickoff',
        startsAt: CarbonImmutable::parse('2026-07-01 10:00'),
    );

    expect($data)
        ->name->toBe('Kickoff')
        ->durationMinutes->toBeNull()
        ->timezone->toBeNull()
        ->description->toBeNull()
        ->meta->toBeNull()
        ->status->toBe(Status::Pending)
        ->participants->toBe([])
        ->recurrence->toBeNull()
        ->preventConflicts->toBeFalse();
});

it('carries participants and metadata', function (): void {
    $participant = new ParticipantData(User::create());

    $data = new AppointmentData(
        name: 'Kickoff',
        startsAt: CarbonImmutable::parse('2026-07-01 10:00'),
        durationMinutes: 30,
        timezone: 'Europe/Bratislava',
        description: 'Notes',
        meta: ['location' => 'HQ'],
        status: Status::Confirmed,
        participants: [$participant],
        preventConflicts: true,
    );

    expect($data)
        ->durationMinutes->toBe(30)
        ->timezone->toBe('Europe/Bratislava')
        ->description->toBe('Notes')
        ->meta->toBe(['location' => 'HQ'])
        ->status->toBe(Status::Confirmed)
        ->participants->toBe([$participant])
        ->preventConflicts->toBeTrue();
});
