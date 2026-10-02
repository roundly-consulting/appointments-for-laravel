<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\DefaultTimezone;
use RoundlyConsulting\Appointments\Support\RecurrenceExpander;

final readonly class ScheduleRecurringAppointmentAction
{
    public function __construct(
        private CreateAppointmentAction $createAppointment,
        private RecurrenceExpander $expander,
    ) {}

    /**
     * Materialise one appointment per occurrence, linked by a shared
     * recurrence group.
     *
     * The rule is expanded in the appointment's own timezone, so a weekly 09:00 stays 09:00
     * local across a daylight-saving change.
     *
     * The series is all-or-nothing: if any occurrence fails (e.g. a
     * SchedulingConflictException for a later week), none are kept.
     *
     * @return Collection<int, Appointment>
     */
    public function execute(AppointmentData $data, RecurrenceData $rule): Collection
    {
        $model = AppointmentModel::class();

        return (new $model)->getConnection()->transaction(function () use ($data, $rule): Collection {
            $group = (string) Str::uuid();
            $start = $data->startsAt->setTimezone($data->timezone ?? DefaultTimezone::resolve());

            /** @var Collection<int, Appointment> $appointments */
            $appointments = new Collection;

            foreach ($this->expander->expand($start, $rule) as $startsAt) {
                $appointment = $this->createAppointment->execute($data->forOccurrence($startsAt));
                $appointment->forceFill(['recurrence_group' => $group])->save();

                $appointments->push($appointment);
            }

            return $appointments;
        });
    }
}
