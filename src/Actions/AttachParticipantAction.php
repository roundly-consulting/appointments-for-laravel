<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

final class AttachParticipantAction
{
    public function execute(Appointment $appointment, ParticipantData $data): Participant
    {
        /** @var Participant $participant */
        $participant = $appointment->participants()->create([
            'participant_type' => $data->participant->getMorphClass(),
            'participant_id' => $data->participant->getKey(),
            'role' => $data->role,
            'meta' => $data->meta,
        ]);

        return $participant;
    }
}
