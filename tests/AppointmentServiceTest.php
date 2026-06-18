<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\AppointmentsService;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('creates appointment via the deprecated service shim', function (): void {
    /** @var AppointmentsService $as */
    $as = resolve(AppointmentsService::class);

    $host = User::create();
    $guest = User::create();

    $appointment = $as->create(
        name: 'Awesome dinner',
        appointmentAt: Carbon::parse('2023-03-01 17:30'),
        description: 'The best chicken wings ever!',
        meta: $meta = collect([
            'location' => 'My house',
            'bring' => 'Beer',
        ]),
        participants: collect([
            [
                $host,
                ['is_host' => true],
            ],
            $guest,
        ]),
    );

    expect($appointment)
        ->toBeInstanceOf(Appointment::class)
        ->name->toBe('Awesome dinner')
        ->description->toBe('The best chicken wings ever!')
        ->starts_at->format('Y-m-d H:i')->toBe('2023-03-01 17:30')
        ->meta->toBeInstanceOf(Collection::class)
        ->meta->toArray()->toBe($meta->toArray())
        ->participants->toBeInstanceOf(Collection::class)
        ->and($appointment->participants->first())
        ->toBeInstanceOf(Participant::class)
        ->participant->toBeInstanceOf(User::class)
        ->participant->getKey()->toBe($host->getKey())
        ->meta->toArray()->toBe(['is_host' => true]);
});

it('adds participant to existing appointment via the shim', function (): void {
    /** @var AppointmentsService $as */
    $as = resolve(AppointmentsService::class);

    $developer = User::create();

    $appointment = Appointment::factory()->create();

    $as->addParticipantToAppointment(
        appointment: $appointment,
        participant: $developer,
        meta: collect([
            'role' => 'Developer',
        ]),
    );

    expect($appointment)
        ->participants->toBeInstanceOf(Collection::class)
        ->and($appointment->participants->first())
        ->toBeInstanceOf(Participant::class)
        ->participant->toBeInstanceOf(User::class)
        ->participant->getKey()->toBe($developer->getKey())
        ->meta->toArray()->toBe(['role' => 'Developer']);
});
