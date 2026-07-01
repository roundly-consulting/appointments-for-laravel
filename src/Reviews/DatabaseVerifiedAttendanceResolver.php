<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Reviews;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * Marks a review as verified when the author is a participant of the appointment
 * and the appointment has been completed. Any other author — a non-participant,
 * or a participant of an appointment that has not completed — stays unverified.
 */
final class DatabaseVerifiedAttendanceResolver implements VerifiedAttendanceResolver
{
    public function verified(Model $author, Model $appointment): bool
    {
        if (! $appointment instanceof Appointment) {
            return false;
        }

        if ($appointment->status !== Status::Completed) {
            return false;
        }

        return $appointment->participants()
            ->whereMorphedTo('participant', $author)
            ->exists();
    }
}
