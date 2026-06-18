<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\ConflictDetector;

final class CreateAppointmentAction
{
    public function __construct(
        private readonly AttachParticipantAction $attachParticipant,
        private readonly ConflictDetector $conflicts,
    ) {}

    public function execute(AppointmentData $data): Appointment
    {
        // Persist instants in UTC so the stored wall-clock value is unambiguous;
        // the appointment's timezone column drives local display.
        $startsAt = $data->startsAt->utc();
        $endsAt = $this->endsAt($startsAt, $data->durationMinutes);

        $this->guardAgainstConflicts($data, $startsAt, $endsAt);

        /** @var class-string<Appointment> $model */
        $model = config('appointments.model', Appointment::class);

        /** @var Appointment $appointment */
        $appointment = (new $model)->newQuery()->create([
            'name' => $data->name,
            'description' => $data->description,
            'status' => $data->status,
            'meta' => $data->meta,
            'timezone' => $data->timezone,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $data->durationMinutes,
        ]);

        foreach ($data->participants as $participant) {
            $this->attachParticipant->execute($appointment, $participant);
        }

        return $appointment->load('participants');
    }

    private function endsAt(CarbonImmutable $startsAt, ?int $durationMinutes): CarbonImmutable
    {
        $minutes = $durationMinutes ?? (int) config('appointments.default_duration_minutes', 60);

        return $startsAt->addMinutes($minutes);
    }

    private function guardAgainstConflicts(AppointmentData $data, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $prevent = $data->preventConflicts || (bool) config('appointments.prevent_conflicts', false);

        if (! $prevent) {
            return;
        }

        foreach ($data->participants as $participant) {
            $conflicts = $this->conflicts->forParticipant($participant->participant, $startsAt, $endsAt);

            if ($conflicts->isNotEmpty()) {
                throw SchedulingConflictException::make($conflicts);
            }
        }
    }
}
