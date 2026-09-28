<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * `Appointments::for($appointment)` — one booking. Every method goes through the manager, so
 * host overrides and `Appointments::fake()` see it.
 */
final readonly class AppointmentHandle
{
    /**
     * @internal build it with `Appointments::for($appointment)`
     */
    public function __construct(
        private AppointmentManager $appointments,
        private Appointment $appointment,
    ) {}

    /**
     * Move the appointment, keeping its duration unless a new one is given. Fires
     * AppointmentRescheduled.
     *
     * @throws SchedulingConflictException when conflicts are prevented (here or in config) and a
     *                                     participant is already booked
     */
    public function reschedule(CarbonInterface $startsAt, ?int $durationMinutes = null, bool $preventConflicts = false): Appointment
    {
        return $this->appointments->rescheduleFor(
            $this->appointment,
            CarbonImmutable::instance($startsAt),
            $durationMinutes,
            $preventConflicts,
        );
    }

    /**
     * Move the status along its lifecycle. Fires AppointmentStatusChanged; a no-op when the
     * appointment already has that status.
     *
     * @throws InvalidStatusTransitionException when the lifecycle forbids the move
     */
    public function transition(Status $to): Appointment
    {
        return $this->appointments->transitionFor($this->appointment, $to);
    }

    public function confirm(): Appointment
    {
        return $this->transition(Status::Confirmed);
    }

    public function cancel(): Appointment
    {
        return $this->transition(Status::Cancelled);
    }

    public function complete(): Appointment
    {
        return $this->transition(Status::Completed);
    }

    public function decline(): Appointment
    {
        return $this->transition(Status::Declined);
    }

    public function markNoShow(): Appointment
    {
        return $this->transition(Status::NoShow);
    }

    /**
     * Add, remove and list the people (or rooms, or any model) taking part.
     */
    public function participants(): AppointmentParticipants
    {
        return new AppointmentParticipants($this->appointments, $this->appointment);
    }

    /**
     * The appointment as a one-event RFC 5545 calendar.
     */
    public function ics(): string
    {
        return $this->appointments->ics([$this->appointment]);
    }
}
