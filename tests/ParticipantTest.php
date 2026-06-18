<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Events\ParticipantCreated;
use RoundlyConsulting\Appointments\Events\ParticipantDeleted;
use RoundlyConsulting\Appointments\Events\ParticipantUpdated;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;

function makeParticipant(array $attributes = []): Participant
{
    $user = User::create();
    $appointment = Appointment::factory()->create();

    return Participant::create(array_merge([
        'appointment_id' => $appointment->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'meta' => collect(['something', 'another']),
    ], $attributes));
}

it('casts meta as collection', function (): void {
    $participant = makeParticipant();

    expect($participant->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()
        ->toBe(['something', 'another']);
});

it('casts role to the ParticipantRole enum', function (): void {
    $participant = makeParticipant(['role' => ParticipantRole::Organiser]);

    expect($participant->fresh()->role)->toBe(ParticipantRole::Organiser);
});

it('reads its table name from config', function (): void {
    expect((new Participant)->getTable())->toBe('appointment_participants');
});

it('has participant entity relationship', function (): void {
    $participant = makeParticipant();

    expect($participant->participant())
        ->toBeInstanceOf(MorphTo::class)
        ->and($participant->participant)
        ->toBeInstanceOf(User::class);
});

it('has appointment entity relationship', function (): void {
    $participant = makeParticipant();

    expect($participant->appointment())
        ->toBeInstanceOf(BelongsTo::class)
        ->and($participant->appointment)
        ->toBeInstanceOf(Appointment::class);
});

it('dispatches ParticipantCreated event', function (): void {
    Event::fake();

    $participant = makeParticipant();

    Event::assertDispatched(ParticipantCreated::class, fn (ParticipantCreated $e) => $e->participant->is($participant));
});

it('dispatches ParticipantUpdated event', function (): void {
    Event::fake();

    $participant = makeParticipant();
    $participant->update(['meta' => null]);

    Event::assertDispatched(ParticipantUpdated::class, fn (ParticipantUpdated $e) => $e->participant->is($participant));
});

it('dispatches ParticipantDeleted event', function (): void {
    Event::fake();

    $participant = makeParticipant();
    $participant->delete();

    Event::assertDispatched(
        ParticipantDeleted::class,
        fn (ParticipantDeleted $e) => $e->participant->is($participant) && $e->participant->trashed(),
    );
});

it('soft deletes a participant', function (): void {
    $participant = makeParticipant();

    $participant->delete();

    expect($participant->trashed())->toBeTrue()
        ->and(Participant::withTrashed()->find($participant->getKey()))->not->toBeNull()
        ->and(Participant::find($participant->getKey()))->toBeNull();
});
