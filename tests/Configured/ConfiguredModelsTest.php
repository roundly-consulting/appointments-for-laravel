<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Tests\Models\CustomAppointment;
use RoundlyConsulting\Appointments\Tests\Models\CustomParticipant;
use RoundlyConsulting\Appointments\Tests\Models\User;

/**
 * S — the model-swap proofs, driven the way a host actually drives them: both
 * `appointments.model` and `appointments.participant` point at host subclasses BEFORE the
 * providers boot (see SwappedModelsTestCase).
 *
 * This is the row's most load-bearing coverage. Appointments is the named case for the
 * retrofit's single biggest bug class: a `hasMany` whose foreign key was derived from the
 * parent CLASS NAME, so a host that swapped Appointment for CustomAppointment got a query
 * for `custom_appointment_id` — a column that does not exist. `Appointment::participants()`
 * now passes `'appointment_id'` explicitly and carries a comment saying why; these tests are
 * what stop that comment becoming the only thing defending it.
 *
 * The existing check in ServiceProviderTest sets `appointments.model` in the test BODY and
 * asserts `instanceof`. Both halves are weak: body-time config is not what a host does and
 * cannot see a boot-time bug, and `instanceof` passes even when the row was CREATED as the
 * packaged class (permissions #31), because re-querying re-hydrates it either way.
 */
it('honours the configured appointment model through the real booking flow', function (): void {
    expect('appointments.model')->toHonourModelSwap(
        CustomAppointment::class,
        function (): array {
            // The real documented flow, not a resolver string check.
            $appointment = Appointments::for('Project kickoff')
                ->startingAt('2026-08-01 17:30')
                ->lasting(90)
                ->create();

            return [$appointment, $appointment->fresh()];
        },
    );
});

/**
 * The participant seam, proven through its own flow. `expectsCreation` stays true: attaching
 * a participant really does create a row, and it must be created AS the host's class or the
 * host's model events never fire.
 */
it('honours the configured participant model when attaching an attendee', function (): void {
    $host = User::create();

    expect('appointments.participant')->toHonourModelSwap(
        CustomParticipant::class,
        function () use ($host): array {
            $appointment = Appointments::for('Workshop')
                ->startingAt('2026-08-02 10:00')
                ->lasting(60)
                ->withParticipant($host, ParticipantRole::Organiser)
                ->create();

            return [$appointment->participants()->first()];
        },
    );
});

/**
 * The FK-derivation bug's exact site: `hasMany` from a SWAPPED parent. If the foreign key
 * were derived from the parent class name this would query `custom_appointment_id` and fail
 * — which is why the concrete class of the related row is asserted, not merely `instanceof`.
 */
it('resolves participants from a swapped parent through the explicit foreign key', function (): void {
    $host = User::create();
    $guest = User::create();

    $appointment = Appointments::for('Kickoff')
        ->startingAt('2026-08-03 09:00')
        ->lasting(60)
        ->withParticipant($host, ParticipantRole::Organiser)
        ->withParticipant($guest)
        ->create();

    expect($appointment::class)->toBe(CustomAppointment::class);

    $participants = $appointment->fresh()->participants;

    expect($participants)->toHaveCount(2)
        // The concrete class, never `instanceof`: the packaged Participant is the host
        // subclass's own parent, so `instanceof` passes on a broken seam.
        ->and($participants->first()::class)->toBe(CustomParticipant::class)
        ->and($participants->first()->appointment_id)->toBe($appointment->getKey())
        // The round trip back up: belongsTo from the swapped child must reach the swapped
        // parent, not the packaged one.
        ->and($participants->first()->appointment::class)->toBe(CustomAppointment::class);
});
