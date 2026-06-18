<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Events\AppointmentRescheduled;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\ConflictDetector;

final class RescheduleAppointmentAction
{
    public function __construct(
        private readonly ConflictDetector $conflicts,
    ) {}

    public function execute(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        ?int $durationMinutes = null,
        bool $preventConflicts = false,
    ): Appointment {
        $previousStartsAt = CarbonImmutable::instance($appointment->starts_at);

        $startsAt = $startsAt->utc();
        $minutes = $durationMinutes ?? $appointment->durationInMinutes();
        $endsAt = $startsAt->addMinutes($minutes);

        $this->guardAgainstConflicts($appointment, $startsAt, $endsAt, $preventConflicts);

        $appointment->starts_at = $startsAt;
        $appointment->duration_minutes = $minutes;
        $appointment->ends_at = $endsAt;
        $appointment->save();

        Event::dispatch(new AppointmentRescheduled($appointment, $previousStartsAt));

        return $appointment;
    }

    private function guardAgainstConflicts(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $preventConflicts,
    ): void {
        $prevent = $preventConflicts || (bool) config('appointments.prevent_conflicts', false);

        if (! $prevent) {
            return;
        }

        foreach ($appointment->participants as $participant) {
            $related = $participant->participant;

            if ($related === null) {
                continue;
            }

            $conflicts = $this->conflicts->forParticipant($related, $startsAt, $endsAt, ignore: $appointment);

            if ($conflicts->isNotEmpty()) {
                throw SchedulingConflictException::make($conflicts);
            }
        }
    }
}
