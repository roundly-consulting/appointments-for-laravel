<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Events\AppointmentRescheduled;
use RoundlyConsulting\Appointments\Exceptions\InvalidScheduleException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Appointments\Support\SchedulingLock;

final readonly class RescheduleAppointmentAction
{
    public function __construct(
        private ConflictDetector $conflicts,
        private SchedulingLock $lock,
    ) {}

    /**
     * With conflicts prevented, the appointment's row and then every participant's row are locked
     * before the clash check, in the same transaction as the move — a concurrent booking or a
     * participant joining meanwhile cannot slip in between.
     *
     * @throws InvalidScheduleException when the duration is not positive
     * @throws SchedulingConflictException when conflicts are prevented and a participant is booked
     */
    public function execute(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        ?int $durationMinutes = null,
        bool $preventConflicts = false,
    ): Appointment {
        $previousStartsAt = CarbonImmutable::instance($appointment->starts_at);

        $startsAt = $startsAt->utc();
        $minutes = $durationMinutes ?? $appointment->durationInMinutes();

        if ($minutes < 1) {
            throw InvalidScheduleException::nonPositiveDuration($minutes);
        }

        $endsAt = $startsAt->addMinutes($minutes);
        $prevent = $this->lock->preventing($preventConflicts);

        // Read up front only to learn which connections need a transaction; the participants that
        // count are re-read under the appointment's lock.
        $participants = $prevent ? $this->participantsOf($appointment) : [];

        return $this->lock->transaction($participants, function () use ($appointment, $startsAt, $endsAt, $minutes, $prevent, $previousStartsAt): Appointment {
            if ($prevent) {
                $this->lock->appointment($appointment);

                $participants = $this->participantsOf($appointment);
                $this->lock->participants($participants);
                $this->guardAgainstConflicts($appointment, $participants, $startsAt, $endsAt);
            }

            $appointment->starts_at = $startsAt;
            $appointment->duration_minutes = $minutes;
            $appointment->ends_at = $endsAt;
            $appointment->save();

            Event::dispatch(new AppointmentRescheduled($appointment, $previousStartsAt));

            return $appointment;
        });
    }

    /**
     * The models taking part, queried rather than taken from the loaded relation: a relation
     * loaded before a participant joined would let the newcomer be double-booked.
     *
     * @return list<Model>
     */
    private function participantsOf(Appointment $appointment): array
    {
        $models = [];

        foreach ($appointment->participants()->with('participant')->get() as $participant) {
            if ($participant->participant instanceof Model) {
                $models[] = $participant->participant;
            }
        }

        return $models;
    }

    /**
     * @param  list<Model>  $participants
     */
    private function guardAgainstConflicts(
        Appointment $appointment,
        array $participants,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): void {
        foreach ($participants as $participant) {
            $conflicts = $this->conflicts->forParticipant($participant, $startsAt, $endsAt, ignore: $appointment);

            if ($conflicts->isNotEmpty()) {
                throw SchedulingConflictException::make($conflicts);
            }
        }
    }
}
