<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

use Carbon\CarbonInterface;

/**
 * An appointment's time does not add up: a duration that is not positive, or an end that does
 * not come after the start. Thrown before anything is written.
 */
final class InvalidScheduleException extends AppointmentsException
{
    public static function nonPositiveDuration(int $minutes): self
    {
        return new self(sprintf('An appointment must last at least one minute; %d given.', $minutes));
    }

    public static function endsBeforeStart(CarbonInterface $startsAt, CarbonInterface $endsAt): self
    {
        return new self(sprintf(
            'An appointment must end after it starts; it starts at %s and ends at %s.',
            $startsAt->toIso8601String(),
            $endsAt->toIso8601String(),
        ));
    }
}
