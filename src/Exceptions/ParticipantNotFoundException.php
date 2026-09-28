<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Models\Appointment;

final class ParticipantNotFoundException extends AppointmentsException
{
    public static function make(Model $participant, Appointment $appointment): self
    {
        return new self(sprintf(
            '[%s #%s] does not take part in appointment [%s].',
            $participant->getMorphClass(),
            (string) $participant->getKey(),
            (string) $appointment->getKey(),
        ));
    }
}
