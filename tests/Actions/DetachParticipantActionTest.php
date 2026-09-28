<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\Actions\DetachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Events\ParticipantDeleted;
use RoundlyConsulting\Appointments\Exceptions\ParticipantNotFoundException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;

beforeEach(function (): void {
    $this->attach = app(AttachParticipantAction::class);
    $this->detach = app(DetachParticipantAction::class);
});

it('soft-deletes the participant row of a model and fires ParticipantDeleted', function (): void {
    Event::fake([ParticipantDeleted::class]);
    $appointment = Appointment::factory()->create();
    $user = User::create();
    $row = $this->attach->execute($appointment, new ParticipantData($user));

    $this->detach->execute($appointment, $user);

    expect($appointment->participants()->count())->toBe(0)
        ->and(Participant::withTrashed()->find($row->getKey())->trashed())->toBeTrue();
    Event::assertDispatched(ParticipantDeleted::class, fn (ParticipantDeleted $event): bool => $event->participant->is($row));
});

it('detaches by participant row', function (): void {
    $appointment = Appointment::factory()->create();
    $row = $this->attach->execute($appointment, new ParticipantData(User::create()));

    $this->detach->execute($appointment, $row);

    expect($appointment->participants()->count())->toBe(0);
});

it('refuses a row of another appointment and leaves it untouched', function (): void {
    $mine = Appointment::factory()->create();
    $theirs = Appointment::factory()->create();
    $foreign = $this->attach->execute($theirs, new ParticipantData(User::create()));

    expect(fn () => $this->detach->execute($mine, $foreign))->toThrow(ParticipantNotFoundException::class);
    expect($theirs->participants()->count())->toBe(1);
});

it('refuses a model that does not take part', function (): void {
    $appointment = Appointment::factory()->create();

    expect(fn () => $this->detach->execute($appointment, User::create()))->toThrow(ParticipantNotFoundException::class);
});

it('refreshes a loaded participants relation', function (): void {
    $appointment = Appointment::factory()->create();
    $user = User::create();
    $this->attach->execute($appointment, new ParticipantData($user));
    $appointment->load('participants');

    $this->detach->execute($appointment, $user);

    expect($appointment->participants)->toBeEmpty();
});
