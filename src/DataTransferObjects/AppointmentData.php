<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\DataTransferObjects;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\InvalidScheduleException;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

final readonly class AppointmentData
{
    /**
     * @param  array<string, mixed>|null  $meta
     * @param  list<ParticipantData>  $participants
     * @param  list<ContactData>  $contacts
     * @param  ?string  $recurrenceGroup  links the occurrences of one series; set by
     *                                    `createRecurring()`
     *
     * @throws InvalidScheduleException when the duration is not positive
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
        public ?string $location = null,
        public ?Coordinates $coordinates = null,
        public array $contacts = [],
        public ?AppointmentApprovalData $approval = null,
        public ?string $recurrenceGroup = null,
    ) {
        if ($this->durationMinutes !== null && $this->durationMinutes < 1) {
            throw InvalidScheduleException::nonPositiveDuration($this->durationMinutes);
        }
    }

    /**
     * The same appointment at another start: one occurrence of a recurring series. Every field
     * carries over — location, coordinates, contacts and approval included — so an occurrence
     * is exactly what scheduling it on its own would create; only the rule itself is dropped,
     * and the occurrence joins the series' group.
     */
    public function forOccurrence(CarbonImmutable $startsAt, ?string $recurrenceGroup = null): self
    {
        return new self(
            name: $this->name,
            startsAt: $startsAt,
            durationMinutes: $this->durationMinutes,
            timezone: $this->timezone,
            description: $this->description,
            meta: $this->meta,
            status: $this->status,
            participants: $this->participants,
            recurrence: null,
            preventConflicts: $this->preventConflicts,
            location: $this->location,
            coordinates: $this->coordinates,
            contacts: $this->contacts,
            approval: $this->approval,
            recurrenceGroup: $recurrenceGroup ?? $this->recurrenceGroup,
        );
    }
}
