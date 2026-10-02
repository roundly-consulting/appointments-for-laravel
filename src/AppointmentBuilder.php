<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentApprovalData;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\InvalidScheduleException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\DefaultTimezone;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

/**
 * `Appointments::schedule($name)` — a fluent appointment. `create()` and `createRecurring()`
 * hand the finished AppointmentData to the manager, so host overrides and `Appointments::fake()`
 * see them.
 */
final class AppointmentBuilder
{
    private ?CarbonImmutable $startsAt = null;

    private ?int $durationMinutes = null;

    /** The raw `until()` input, resolved against the start once both are known. */
    private CarbonInterface|string|null $endsAt = null;

    private ?string $timezone = null;

    private ?string $description = null;

    private Status $status = Status::Pending;

    private bool $preventConflicts = false;

    private ?RecurrenceData $recurrence = null;

    /** @var array<string, mixed>|null */
    private ?array $meta = null;

    private ?string $location = null;

    private ?Coordinates $coordinates = null;

    /** @var list<ParticipantData> */
    private array $participants = [];

    /** @var list<ContactData> */
    private array $contacts = [];

    /** @var list<Model> */
    private array $approvers = [];

    private ApprovalRule $approvalRule = ApprovalRule::Unanimous;

    private ?int $approvalQuorum = null;

    /** @var list<StageDefinition> */
    private array $approvalStages = [];

    private ?string $approvalWorkflow = null;

    /** @var list<list<Model>> */
    private array $approvalStageApprovers = [];

    private bool $rejectOnStageRejection = true;

    /**
     * @internal build it with `Appointments::schedule($name)`
     */
    public function __construct(
        private readonly AppointmentManager $appointments,
        private readonly string $name,
    ) {}

    /**
     * When it starts. A Carbon keeps its instant; a string without an offset is read in
     * `$timezone`, else the zone given earlier, else `appointments.timezone` / `app.timezone`.
     * `$timezone` is also stored on the appointment for local display.
     */
    public function startingAt(CarbonInterface|string $at, ?string $timezone = null): self
    {
        $this->timezone = $timezone ?? $this->timezone;
        $this->startsAt = $this->moment($at);

        return $this;
    }

    /**
     * How long it lasts, in minutes (at least one). Replaces an earlier `until()`.
     *
     * @throws InvalidScheduleException when the duration is not positive
     */
    public function lasting(int $minutes): self
    {
        if ($minutes < 1) {
            throw InvalidScheduleException::nonPositiveDuration($minutes);
        }

        $this->durationMinutes = $minutes;
        $this->endsAt = null;

        return $this;
    }

    /**
     * When it ends — read like `startingAt()` and measured against the start whenever that is
     * given, before or after this call. Replaces an earlier `lasting()`; `create()` throws
     * InvalidScheduleException unless the end comes after the start.
     */
    public function until(CarbonInterface|string $at): self
    {
        $this->endsAt = $at;
        $this->durationMinutes = null;

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

    /**
     * Set the appointment's venue coordinates, and optionally a human venue name.
     */
    public function located(float $latitude, float $longitude, ?string $venue = null): self
    {
        $this->coordinates = new Coordinates($latitude, $longitude);

        if ($venue !== null) {
            $this->location = $venue;
        }

        return $this;
    }

    /**
     * Set the appointment's venue coordinates from a Coordinates value object.
     */
    public function at(Coordinates $coordinates): self
    {
        $this->coordinates = $coordinates;

        return $this;
    }

    /**
     * Set the human-readable venue / location string.
     */
    public function venue(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function withContactEmail(string $email, ?string $label = null, bool $primary = true): self
    {
        $this->contacts[] = new ContactData(
            type: ContactType::Email,
            value: $email,
            label: $label,
            isPrimary: $primary,
        );

        return $this;
    }

    public function withContactPhone(string $phone, ?string $label = null, bool $primary = true): self
    {
        $this->contacts[] = new ContactData(
            type: ContactType::Phone,
            value: $phone,
            label: $label,
            isPrimary: $primary,
        );

        return $this;
    }

    /**
     * Require booking-request sign-off from the given approvers before the
     * appointment is confirmed. The appointment is created Pending and an approval
     * request is opened; the SyncAppointmentStatusFromApproval listener drives it to
     * Confirmed/Declined/Cancelled as the request resolves.
     *
     * @param  Model|iterable<array-key, Model>  $approvers
     */
    public function requireApprovalFrom(Model|iterable $approvers, ApprovalRule $rule = ApprovalRule::Unanimous, ?int $quorum = null): self
    {
        $approvers = $approvers instanceof Model ? [$approvers] : $approvers;

        $this->approvers = array_values([...$approvers]);
        $this->approvalRule = $rule;
        $this->approvalQuorum = $quorum;

        return $this;
    }

    public function approvalRule(ApprovalRule $rule): self
    {
        $this->approvalRule = $rule;

        return $this;
    }

    public function approvalQuorum(?int $quorum): self
    {
        $this->approvalQuorum = $quorum;

        return $this;
    }

    /**
     * Open the booking approval as a sequential, multi-stage pipeline. Each stage
     * opens only once the previous one clears.
     *
     * @param  list<StageDefinition>  $stages
     */
    public function approvalStages(array $stages): self
    {
        $this->approvalStages = $stages;

        return $this;
    }

    public function rejectOnStageRejection(bool $reject = true): self
    {
        $this->rejectOnStageRejection = $reject;

        return $this;
    }

    /**
     * Open the booking approval from a named workflow preset
     * (config('approvals.workflows')). Provide flat approvers via
     * requireApprovalFrom(), or one group per stage via approvalStageApprovers().
     */
    public function approvalWorkflow(?string $name): self
    {
        $this->approvalWorkflow = $name;

        return $this;
    }

    /**
     * Approver groups, one per stage, for a staged workflow preset.
     *
     * @param  list<list<Model>>  $groups
     */
    public function approvalStageApprovers(array $groups): self
    {
        $this->approvalStageApprovers = $groups;

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
        return $this->appointments->create($this->toData());
    }

    /**
     * One appointment per occurrence of the `recurring()` rule, all-or-nothing; without a rule,
     * the single appointment.
     *
     * @return Collection<int, Appointment>
     */
    public function createRecurring(): Collection
    {
        return $this->appointments->createRecurring($this->toData());
    }

    private function toData(): AppointmentData
    {
        $startsAt = $this->startsAt ?? CarbonImmutable::now();

        return new AppointmentData(
            name: $this->name,
            startsAt: $startsAt,
            durationMinutes: $this->durationMinutes($startsAt),
            timezone: $this->timezone,
            description: $this->description,
            meta: $this->meta,
            status: $this->status,
            participants: $this->participants,
            recurrence: $this->recurrence,
            preventConflicts: $this->preventConflicts,
            location: $this->location,
            coordinates: $this->coordinates,
            contacts: $this->contacts,
            approval: $this->approvalData(),
        );
    }

    /**
     * The explicit duration, or the one `until()` implies against the final start.
     *
     * @throws InvalidScheduleException when the end does not come after the start
     */
    private function durationMinutes(CarbonImmutable $startsAt): ?int
    {
        if ($this->endsAt === null) {
            return $this->durationMinutes;
        }

        $endsAt = $this->moment($this->endsAt);

        if (! $endsAt->greaterThan($startsAt)) {
            throw InvalidScheduleException::endsBeforeStart($startsAt, $endsAt);
        }

        return (int) $startsAt->diffInMinutes($endsAt);
    }

    private function moment(CarbonInterface|string $at): CarbonImmutable
    {
        return $at instanceof CarbonInterface
            ? CarbonImmutable::instance($at)
            : CarbonImmutable::parse($at, $this->timezone ?? DefaultTimezone::resolve());
    }

    private function approvalData(): ?AppointmentApprovalData
    {
        $hasApproval = $this->approvers !== []
            || $this->approvalStages !== []
            || $this->approvalStageApprovers !== []
            || $this->approvalWorkflow !== null;

        if (! $hasApproval) {
            return null;
        }

        return new AppointmentApprovalData(
            approvers: $this->approvers,
            rule: $this->approvalRule,
            quorum: $this->approvalQuorum,
            stages: $this->approvalStages,
            workflow: $this->approvalWorkflow,
            stageApprovers: $this->approvalStageApprovers,
            rejectOnStageRejection: $this->rejectOnStageRejection,
        );
    }
}
