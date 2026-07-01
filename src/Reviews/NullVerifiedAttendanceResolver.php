<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Reviews;

use Illuminate\Database\Eloquent\Model;

/**
 * A resolver that never verifies attendance — every review stays unverified.
 * Bind this via config when appointment reviews should not be trust-stamped.
 */
final class NullVerifiedAttendanceResolver implements VerifiedAttendanceResolver
{
    public function verified(Model $author, Model $appointment): bool
    {
        return false;
    }
}
