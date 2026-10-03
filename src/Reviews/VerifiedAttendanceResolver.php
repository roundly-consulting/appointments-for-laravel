<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Reviews;

use Illuminate\Database\Eloquent\Model;

/**
 * Decides whether a review author actually attended the appointment they are
 * reviewing, so the review can be stamped as verified. The bound implementation
 * is configured via `appointments.reviews.verified_attendance_resolver`; not set
 * (absent, null or blank) binds {@see DatabaseVerifiedAttendanceResolver}.
 */
interface VerifiedAttendanceResolver
{
    public function verified(Model $author, Model $appointment): bool;
}
