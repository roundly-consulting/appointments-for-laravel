<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\DataTransferObjects;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Enums\Frequency;

final readonly class RecurrenceData
{
    /**
     * @param  list<int>  $byWeekday  ISO-8601 weekday numbers (1 = Monday … 7 = Sunday)
     */
    public function __construct(
        public Frequency $frequency,
        public int $interval = 1,
        public ?CarbonImmutable $until = null,
        public ?int $count = null,
        public array $byWeekday = [],
    ) {}
}
