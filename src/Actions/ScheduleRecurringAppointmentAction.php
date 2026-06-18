<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\RecurrenceExpander;

final class ScheduleRecurringAppointmentAction
{
    public function __construct(
        private readonly CreateAppointmentAction $createAppointment,
        private readonly RecurrenceExpander $expander,
    ) {}

    /**
     * Materialise one appointment per occurrence, linked by a shared
     * recurrence group.
     *
     * @return Collection<int, Appointment>
     */
    public function execute(AppointmentData $data, RecurrenceData $rule): Collection
    {
        $group = (string) Str::uuid();
        $occurrences = $this->expander->expand($data->startsAt, $rule);

        /** @var Collection<int, Appointment> $appointments */
        $appointments = new Collection;

        foreach ($occurrences as $startsAt) {
            $occurrence = new AppointmentData(
                name: $data->name,
                startsAt: $startsAt,
                durationMinutes: $data->durationMinutes,
                timezone: $data->timezone,
                description: $data->description,
                meta: $data->meta,
                status: $data->status,
                participants: $data->participants,
                preventConflicts: $data->preventConflicts,
            );

            $appointment = $this->createAppointment->execute($occurrence);
            $appointment->forceFill(['recurrence_group' => $group])->save();

            $appointments->push($appointment);
        }

        return $appointments;
    }
}
