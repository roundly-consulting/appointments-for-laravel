<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Exceptions\ParticipantNotFoundException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

/**
 * Remove a participant from an appointment: by the participating model, or by one of the
 * appointment's own participant rows. Rows are soft-deleted one by one, so each fires
 * ParticipantDeleted.
 */
final readonly class DetachParticipantAction
{
    /**
     * @throws ParticipantNotFoundException when it does not take part in this appointment — a
     *                                      participant row of another appointment included
     */
    public function execute(Appointment $appointment, Model $participant): void
    {
        $query = $appointment->participants();

        $rows = $participant instanceof Participant
            ? $query->whereKey($participant->getKey())->get()
            : $query->whereMorphedTo('participant', $participant)->get();

        if ($rows->isEmpty()) {
            throw ParticipantNotFoundException::make($participant, $appointment);
        }

        $rows->each(static fn (Participant $row): ?bool => $row->delete());

        if ($appointment->relationLoaded('participants')) {
            $appointment->load('participants');
        }
    }
}
