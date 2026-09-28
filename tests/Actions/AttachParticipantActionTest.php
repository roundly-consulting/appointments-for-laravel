<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Events\ParticipantCreated;
use RoundlyConsulting\Appointments\Events\ParticipantUpdated;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('attaches a participant to an appointment', function (): void {
    $appointment = Appointment::factory()->create();
    $user = User::create();

    $participant = app(AttachParticipantAction::class)->execute(
        $appointment,
        new ParticipantData($user, ParticipantRole::Attendee, ['note' => 'vip']),
    );

    expect($participant)
        ->toBeInstanceOf(Participant::class)
        ->appointment_id->toBe($appointment->id)
        ->role->toBe(ParticipantRole::Attendee)
        ->and($participant->participant->is($user))->toBeTrue()
        ->and($participant->meta->toArray())->toBe(['note' => 'vip']);
});

it('refuses a model that already takes part', function (): void {
    $appointment = Appointment::factory()->create();
    $user = User::create();
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($user));

    expect(fn () => app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($user)))
        ->toThrow(DuplicateParticipantException::class);
});

it('restores a removed participant with the new role and meta', function (): void {
    Event::fake([ParticipantCreated::class, ParticipantUpdated::class]);
    $appointment = Appointment::factory()->create();
    $user = User::create();
    $first = app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($user, ParticipantRole::Attendee));
    $first->delete();

    $again = app(AttachParticipantAction::class)->execute(
        $appointment,
        new ParticipantData($user, ParticipantRole::Organiser, ['note' => 'back']),
    );

    expect($again->getKey())->toBe($first->getKey())
        ->and($again->trashed())->toBeFalse()
        ->and($again->fresh()->role)->toBe(ParticipantRole::Organiser)
        ->and($again->fresh()->meta->toArray())->toBe(['note' => 'back']);
    Event::assertDispatchedTimes(ParticipantCreated::class, 1);
    Event::assertDispatched(ParticipantUpdated::class);
});

it('checks conflicts when asked or configured, and ignores the appointment itself', function (): void {
    $user = User::create();
    $busy = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00'),
        'duration_minutes' => 60,
    ]);
    app(AttachParticipantAction::class)->execute($busy, new ParticipantData($user));
    $overlapping = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:30'),
        'duration_minutes' => 60,
    ]);

    expect(fn () => app(AttachParticipantAction::class)->execute($overlapping, new ParticipantData($user), preventConflicts: true))
        ->toThrow(SchedulingConflictException::class);

    config()->set('appointments.prevent_conflicts', true);

    expect(fn () => app(AttachParticipantAction::class)->execute($overlapping, new ParticipantData($user)))
        ->toThrow(SchedulingConflictException::class);

    $later = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 10:00'),
        'duration_minutes' => 30,
    ]);

    expect(app(AttachParticipantAction::class)->execute($later, new ParticipantData($user)))->toBeInstanceOf(Participant::class);
});

it('allows a conflict when prevention is off', function (): void {
    $user = User::create();
    $busy = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-01 09:00')]);
    app(AttachParticipantAction::class)->execute($busy, new ParticipantData($user));
    $overlapping = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-01 09:00')]);

    expect(app(AttachParticipantAction::class)->execute($overlapping, new ParticipantData($user)))->toBeInstanceOf(Participant::class);
});
