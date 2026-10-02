<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

/**
 * A recurrence rule that cannot describe a series: an interval or count below one, or a weekday
 * outside ISO 1 (Monday) – 7 (Sunday).
 */
final class InvalidRecurrenceException extends AppointmentsException
{
    public static function interval(int $interval): self
    {
        return new self(sprintf('A recurrence interval must be at least 1; %d given.', $interval));
    }

    public static function count(int $count): self
    {
        return new self(sprintf('A recurrence count must be at least 1; %d given.', $count));
    }

    public static function weekday(int $weekday): self
    {
        return new self(sprintf('A recurrence weekday must be an ISO weekday from 1 (Monday) to 7 (Sunday); %d given.', $weekday));
    }
}
