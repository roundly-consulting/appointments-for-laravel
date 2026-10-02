<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Appointments\Support\SchedulingLock;

/**
 * Add a participant to an existing appointment. A participant removed earlier is restored
 * (ParticipantUpdated); a new one is inserted (ParticipantCreated).
 */
final readonly class AttachParticipantAction
{
    public function __construct(
        private ConflictDetector $conflicts,
        private SchedulingLock $lock,
    ) {}

    /**
     * With conflicts prevented, the appointment's row and the model's row are locked before the
     * clash check, in the same transaction as the write — a concurrent booking or reschedule
     * cannot slip in between.
     *
     * @throws DuplicateParticipantException when the model already takes part
     * @throws SchedulingConflictException when conflicts are prevented (argument or
     *                                     `appointments.prevent_conflicts`) and the model is
     *                                     booked elsewhere in the appointment's window
     */
    public function execute(Appointment $appointment, ParticipantData $data, bool $preventConflicts = false): Participant
    {
        $prevent = $this->lock->preventing($preventConflicts);

        $participant = $this->lock->transaction([$data->participant], function () use ($appointment, $data, $prevent): Participant {
            // The window as stored now: a reschedule may have moved it since it was loaded.
            $window = $prevent ? $this->lock->appointment($appointment) : $appointment;

            if ($prevent) {
                $this->lock->participants([$data->participant]);
            }

            // Trashed rows included: the (appointment, participant) pair is unique in the table, so
            // a participant removed earlier comes back by restoring its row, not by a second insert.
            /** @var Participant|null $existing */
            $existing = $appointment->participants()->withTrashed()
                ->whereMorphedTo('participant', $data->participant)
                ->first();

            if ($existing !== null && ! $existing->trashed()) {
                throw DuplicateParticipantException::make($data->participant, $appointment);
            }

            if ($prevent) {
                $this->guardAgainstConflicts($window, $data);
            }

            return $existing !== null
                ? $this->restore($existing, $data)
                : $this->insert($appointment, $data);
        });

        // A loaded relation would now be stale (the ICS export reads it).
        if ($appointment->relationLoaded('participants')) {
            $appointment->load('participants');
        }

        return $participant;
    }

    private function insert(Appointment $appointment, ParticipantData $data): Participant
    {
        try {
            /** @var Participant $participant */
            $participant = $appointment->participants()->create($data->toAttributes());
        } catch (UniqueConstraintViolationException) {
            // A concurrent add of the same model won the unique (appointment, participant) index.
            throw DuplicateParticipantException::make($data->participant, $appointment);
        }

        return $participant;
    }

    /**
     * Bring a removed participant back with the new role and meta (fires ParticipantUpdated).
     */
    private function restore(Participant $participant, ParticipantData $data): Participant
    {
        $participant->forceFill(['role' => $data->role, 'meta' => $data->meta])->restore();

        return $participant;
    }

    private function guardAgainstConflicts(Appointment $appointment, ParticipantData $data): void
    {
        $startsAt = CarbonImmutable::instance($appointment->starts_at);
        $endsAt = $appointment->ends_at !== null
            ? CarbonImmutable::instance($appointment->ends_at)
            : $startsAt->addMinutes($appointment->durationInMinutes());

        $conflicts = $this->conflicts->forParticipant($data->participant, $startsAt, $endsAt, ignore: $appointment);

        if ($conflicts->isNotEmpty()) {
            throw SchedulingConflictException::make($conflicts);
        }
    }
}
