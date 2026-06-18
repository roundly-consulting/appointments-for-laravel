<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\DataTransferObjects;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Enums\Status;

final readonly class AppointmentData
{
    /**
     * @param  array<string, mixed>|null  $meta
     * @param  list<ParticipantData>  $participants
     */
    public function __construct(
        public string $name,
        public CarbonImmutable $startsAt,
        public ?int $durationMinutes = null,
        public ?string $timezone = null,
        public ?string $description = null,
        public ?array $meta = null,
        public Status $status = Status::Pending,
        public array $participants = [],
        public ?RecurrenceData $recurrence = null,
        public bool $preventConflicts = false,
    ) {}
}
