<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Events\ParticipantCreated;
use RoundlyConsulting\Appointments\Events\ParticipantDeleted;
use RoundlyConsulting\Appointments\Events\ParticipantUpdated;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('casts meta as collection', function () {
    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ]);

    expect($participant->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()
        ->toBe(['something', 'another']);
});

it('has participant entity relationship', function () {
    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ]);

    expect($participant->participant())
        ->toBeInstanceOf(MorphTo::class)
        ->and($participant->participant)
        ->toBeInstanceOf(User::class)
        ->getKey()
        ->toBe($user->getKey());
});

it('has appointment entity relationship', function () {
    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ]);

    expect($participant->appointment())
        ->toBeInstanceOf(BelongsTo::class)
        ->and($participant->appointment)
        ->toBeInstanceOf(Appointment::class)
        ->getKey()
        ->toBe($appointment->getKey());
});

it('dispatches ParticipantCreated event', function () {
    Event::fake();

    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ]);

    Event::assertDispatched(ParticipantCreated::class, fn (ParticipantCreated $e) => $e->participant->is($participant));
});

it('dispatches ParticipantUpdated event', function () {
    Event::fake();

    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ]);

    $participant->update(['meta' => null]);

    Event::assertDispatched(ParticipantUpdated::class, fn (ParticipantUpdated $e) => $e->participant->is($participant));
});

it('dispatches ParticipantDeleted event', function () {
    Event::fake();

    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ]);

    $participant->delete();

    Event::assertDispatched(
        ParticipantDeleted::class,
        fn (ParticipantDeleted $e) => $e->participant->is($participant) && $e->participant->trashed(),
    );
});

it('soft deletes a participant', function () {
    $user = User::create();
    $appointment = Appointment::factory()->create();
    $participant = Participant::create([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $participant->delete();

    expect($participant->trashed())->toBeTrue()
        ->and(Participant::withTrashed()->find($participant->getKey()))->not->toBeNull()
        ->and(Participant::find($participant->getKey()))->toBeNull();
});
