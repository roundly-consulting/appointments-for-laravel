<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

use RoundlyConsulting\Appointments\Models\Appointment;

final class CannotReviewAppointmentException extends AppointmentsException
{
    public static function unverifiedAttendance(Appointment $appointment): self
    {
        return new self(sprintf(
            'The author did not attend appointment [%s], so it cannot be reviewed.',
            (string) $appointment->getKey(),
        ));
    }
}
