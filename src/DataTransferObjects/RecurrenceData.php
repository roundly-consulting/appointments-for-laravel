<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\DataTransferObjects;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Exceptions\InvalidRecurrenceException;

final readonly class RecurrenceData
{
    /**
     * @param  int  $interval  every Nth day / week / month (at least 1)
     * @param  ?CarbonImmutable  $until  the last moment an occurrence may start; a value at
     *                                   midnight is read as a date and includes that whole day
     * @param  ?int  $count  how many occurrences at most (at least 1)
     * @param  list<int>  $byWeekday  ISO-8601 weekday numbers (1 = Monday … 7 = Sunday): the
     *                                days of each active day / week / month that occur
     *
     * @throws InvalidRecurrenceException when the interval or count is below one, or a weekday
     *                                    is outside 1–7
     */
    public function __construct(
        public Frequency $frequency,
        public int $interval = 1,
        public ?CarbonImmutable $until = null,
        public ?int $count = null,
        public array $byWeekday = [],
    ) {
        if ($this->interval < 1) {
            throw InvalidRecurrenceException::interval($this->interval);
        }

        if ($this->count !== null && $this->count < 1) {
            throw InvalidRecurrenceException::count($this->count);
        }

        foreach ($this->byWeekday as $weekday) {
            if ($weekday < 1 || $weekday > 7) {
                throw InvalidRecurrenceException::weekday($weekday);
            }
        }
    }
}
