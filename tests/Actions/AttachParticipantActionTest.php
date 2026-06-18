<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
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
