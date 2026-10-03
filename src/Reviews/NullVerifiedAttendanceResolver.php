<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Reviews;

use Illuminate\Database\Eloquent\Model;

/**
 * A resolver that never verifies attendance — every review stays unverified.
 * Never the default: name it in `appointments.reviews.verified_attendance_resolver`
 * when appointment reviews should not be trust-stamped. Not set binds
 * {@see DatabaseVerifiedAttendanceResolver}.
 */
final class NullVerifiedAttendanceResolver implements VerifiedAttendanceResolver
{
    public function verified(Model $author, Model $appointment): bool
    {
        return false;
    }
}
