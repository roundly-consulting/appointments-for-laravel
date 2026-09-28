<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Models\Appointment;

final class DuplicateParticipantException extends AppointmentsException
{
    public static function make(Model $participant, ?Appointment $appointment = null): self
    {
        return new self(sprintf(
            '[%s #%s] already takes part in %s.',
            $participant->getMorphClass(),
            (string) $participant->getKey(),
            $appointment?->exists === true ? sprintf('appointment [%s]', (string) $appointment->getKey()) : 'this appointment',
        ));
    }
}
