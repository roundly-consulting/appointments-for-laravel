<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

final class ConflictDetector
{
    /**
     * Appointments that overlap the given window for the given participant.
     *
     * Cancelled and declined appointments are ignored, as is the optionally
     * excluded appointment (used when rescheduling an appointment against
     * itself).
     *
     * @return Collection<int, Appointment>
     */
    public function forParticipant(
        Model $participant,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignore = null,
    ): Collection {
        $model = AppointmentModel::class();

        $query = $model::query()
            ->forParticipant($participant)
            ->overlapping($start, $end)
            ->whereNotIn('status', [Status::Cancelled->value, Status::Declined->value]);

        if ($ignore !== null && $ignore->exists) {
            $query->whereKeyNot($ignore->getKey());
        }

        /** @var Collection<int, Appointment> $conflicts */
        $conflicts = $query->get();

        return $conflicts;
    }

    public function hasConflict(
        Model $participant,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignore = null,
    ): bool {
        return $this->forParticipant($participant, $start, $end, $ignore)->isNotEmpty();
    }
}
