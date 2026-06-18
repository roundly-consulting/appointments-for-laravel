<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\Actions\ScheduleRecurringAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

final class AppointmentBuilder
{
    private ?CarbonImmutable $startsAt = null;

    private ?int $durationMinutes = null;

    private ?string $timezone = null;

    private ?string $description = null;

    private Status $status = Status::Pending;

    private bool $preventConflicts = false;

    private ?RecurrenceData $recurrence = null;

    /** @var array<string, mixed>|null */
    private ?array $meta = null;

    /** @var list<ParticipantData> */
    private array $participants = [];

    public function __construct(
        private readonly string $name,
        private readonly CreateAppointmentAction $createAppointment,
        private readonly ScheduleRecurringAppointmentAction $scheduleRecurring,
    ) {}

    public function startingAt(CarbonInterface|string $at, ?string $timezone = null): self
    {
        $this->timezone = $timezone ?? $this->timezone;

        $this->startsAt = $timezone !== null
            ? CarbonImmutable::parse($at, $timezone)
            : CarbonImmutable::parse($at);

        return $this;
    }

    public function lasting(int $minutes): self
    {
        $this->durationMinutes = $minutes;

        return $this;
    }

    public function until(CarbonInterface|string $at): self
    {
        $end = CarbonImmutable::parse($at, $this->timezone);

        if ($this->startsAt !== null) {
            $this->durationMinutes = (int) $this->startsAt->diffInMinutes($end);
        }

        return $this;
    }

    public function describedAs(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function withStatus(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withParticipant(Model $participant, ?ParticipantRole $role = null, array $meta = []): self
    {
        $this->participants[] = new ParticipantData(
            participant: $participant,
            role: $role,
            meta: $meta === [] ? null : $meta,
        );

        return $this;
    }

    public function recurring(RecurrenceData $rule): self
    {
        $this->recurrence = $rule;

        return $this;
    }

    public function preventConflicts(bool $prevent = true): self
    {
        $this->preventConflicts = $prevent;

        return $this;
    }

    public function create(): Appointment
    {
        return $this->createAppointment->execute($this->toData());
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function createRecurring(): Collection
    {
        if ($this->recurrence === null) {
            return new Collection([$this->create()]);
        }

        return $this->scheduleRecurring->execute($this->toData(), $this->recurrence);
    }

    private function toData(): AppointmentData
    {
        return new AppointmentData(
            name: $this->name,
            startsAt: $this->startsAt ?? CarbonImmutable::now(),
            durationMinutes: $this->durationMinutes,
            timezone: $this->timezone,
            description: $this->description,
            meta: $this->meta,
            status: $this->status,
            participants: $this->participants,
            recurrence: $this->recurrence,
            preventConflicts: $this->preventConflicts,
        );
    }
}
