<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\Actions\DetachParticipantAction;
use RoundlyConsulting\Appointments\Actions\RescheduleAppointmentAction;
use RoundlyConsulting\Appointments\Actions\ScheduleRecurringAppointmentAction;
use RoundlyConsulting\Appointments\Actions\TransitionAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Appointments\Support\Ics\IcsGenerator;
use RoundlyConsulting\Appointments\Support\RecurrenceExpander;
use RoundlyConsulting\Appointments\Testing\AppointmentsFake;

/**
 * The appointments API: the root behind the {@see Appointments} facade, and the class to inject
 * when you prefer dependency injection.
 *
 * - `schedule($name)` starts the fluent builder; `create()` / `createRecurring()` take a DTO.
 * - `for($appointment)` scopes reschedule, status changes, participants and ICS to one booking.
 * - `conflicts()` / `isAvailable()` answer "is this person free?"; `ics()` renders a feed;
 *   `occurrences()` previews a recurrence rule without writing anything.
 *
 * Every mutation — from the facade, an injected manager, the builder, a handle or an
 * `Appointment` model method — funnels through one method here that resolves its action from
 * the container, so a host override applies everywhere and {@see AppointmentsFake} sees it.
 *
 * Not final on purpose: {@see AppointmentsFake} extends it so a constructor-injected manager
 * receives the fake under `Appointments::fake()`.
 */
class AppointmentManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Start a fluent appointment: `->startingAt()->lasting()->withParticipant()->create()`.
     */
    public function schedule(string $name): AppointmentBuilder
    {
        return new AppointmentBuilder($this, $name);
    }

    public function create(AppointmentData $data): Appointment
    {
        return $this->container->make(CreateAppointmentAction::class)->execute($data);
    }

    /**
     * Materialise a recurring series, all-or-nothing. The rule falls back to the DTO's own
     * `recurrence`; with neither, the "series" is the single appointment.
     *
     * @return Collection<int, Appointment>
     */
    public function createRecurring(AppointmentData $data, ?RecurrenceData $rule = null): Collection
    {
        $rule ??= $data->recurrence;

        if ($rule === null) {
            return new Collection([$this->container->make(CreateAppointmentAction::class)->execute($data)]);
        }

        return $this->container->make(ScheduleRecurringAppointmentAction::class)->execute($data, $rule);
    }

    /**
     * One appointment: reschedule it, move its status, manage its participants, export it.
     */
    public function for(Appointment $appointment): AppointmentHandle
    {
        return new AppointmentHandle($this, $appointment);
    }

    /**
     * The participant's appointments overlapping the window. Cancelled and declined bookings
     * never conflict; `$ignore` leaves one appointment out (the one being moved).
     *
     * @return Collection<int, Appointment>
     */
    public function conflicts(
        Model $participant,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignore = null,
    ): Collection {
        return $this->container->make(ConflictDetector::class)->forParticipant($participant, $start, $end, $ignore);
    }

    /**
     * Whether the participant is free for the whole window.
     */
    public function isAvailable(
        Model $participant,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignore = null,
    ): bool {
        return ! $this->container->make(ConflictDetector::class)->hasConflict($participant, $start, $end, $ignore);
    }

    /**
     * One RFC 5545 calendar holding a VEVENT per appointment — a subscribable feed.
     *
     * @param  iterable<Appointment>  $appointments
     */
    public function ics(iterable $appointments): string
    {
        return $this->container->make(IcsGenerator::class)->forCollection($appointments);
    }

    /**
     * The start times a recurrence rule expands to from `$start`, capped by
     * `appointments.recurrence.max_occurrences`. Nothing is written.
     *
     * @return list<CarbonImmutable>
     */
    public function occurrences(CarbonInterface $start, RecurrenceData $rule): array
    {
        return $this->container->make(RecurrenceExpander::class)->expand(CarbonImmutable::instance($start), $rule);
    }

    /*
     * The operations behind for(). They are public only so the handle can reach them, and
     * @internal so the facade never documents them. They are also the ONE place
     * AppointmentsFake overrides: every call — through the facade, an injected manager, a
     * handle or an Appointment model method — lands here.
     */

    /**
     * @internal the body of `for($appointment)->reschedule()`
     */
    public function rescheduleFor(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        ?int $durationMinutes = null,
        bool $preventConflicts = false,
    ): Appointment {
        return $this->container->make(RescheduleAppointmentAction::class)
            ->execute($appointment, $startsAt, $durationMinutes, $preventConflicts);
    }

    /**
     * @internal the body of `for($appointment)->transition()` and its `confirm()`-style shortcuts
     */
    public function transitionFor(Appointment $appointment, Status $to): Appointment
    {
        return $this->container->make(TransitionAppointmentAction::class)->execute($appointment, $to);
    }

    /**
     * @internal the body of `for($appointment)->participants()->add()`
     */
    public function addParticipantFor(Appointment $appointment, ParticipantData $data, bool $preventConflicts = false): Participant
    {
        return $this->container->make(AttachParticipantAction::class)->execute($appointment, $data, $preventConflicts);
    }

    /**
     * @internal the body of `for($appointment)->participants()->remove()`
     */
    public function removeParticipantFor(Appointment $appointment, Model $participant): void
    {
        $this->container->make(DetachParticipantAction::class)->execute($appointment, $participant);
    }
}
