<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Exceptions\ParticipantNotFoundException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

/**
 * `Appointments::for($appointment)->participants()` — who takes part in one booking. Writes go
 * through the manager, so host overrides and `Appointments::fake()` see them.
 */
final readonly class AppointmentParticipants
{
    /**
     * @internal build it with `Appointments::for($appointment)->participants()`
     */
    public function __construct(
        private AppointmentManager $appointments,
        private Appointment $appointment,
    ) {}

    /**
     * Add any model as a participant. Fires ParticipantCreated.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws DuplicateParticipantException when the model already takes part
     * @throws SchedulingConflictException when conflicts are prevented (here or in config) and the
     *                                     model is booked elsewhere at that time
     */
    public function add(
        Model $participant,
        ?ParticipantRole $role = null,
        array $meta = [],
        bool $preventConflicts = false,
    ): Participant {
        return $this->appointments->addParticipantFor(
            $this->appointment,
            new ParticipantData($participant, $role, $meta === [] ? null : $meta),
            $preventConflicts,
        );
    }

    /**
     * Remove a participant — the participating model, or one of this appointment's participant
     * rows. Fires ParticipantDeleted.
     *
     * @throws ParticipantNotFoundException when it does not take part in this appointment
     *                                      (a row of another appointment included)
     */
    public function remove(Model $participant): void
    {
        $this->appointments->removeParticipantFor($this->appointment, $participant);
    }

    /**
     * Whether the model (or participant row) takes part in this appointment.
     */
    public function has(Model $participant): bool
    {
        $query = $this->appointment->participants();

        if ($participant instanceof Participant) {
            return $query->whereKey($participant->getKey())->exists();
        }

        return $query->whereMorphedTo('participant', $participant)->exists();
    }

    /**
     * The participant rows (role and meta included), their models eager-loaded.
     *
     * @return Collection<int, Participant>
     */
    public function all(): Collection
    {
        return $this->appointment->participants()->with('participant')->oldest('id')->get();
    }
}
