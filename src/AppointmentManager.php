<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\Actions\RescheduleAppointmentAction;
use RoundlyConsulting\Appointments\Actions\ScheduleRecurringAppointmentAction;
use RoundlyConsulting\Appointments\Actions\TransitionAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

final class AppointmentManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function for(string $name): AppointmentBuilder
    {
        return new AppointmentBuilder(
            $name,
            $this->container->make(CreateAppointmentAction::class),
            $this->container->make(ScheduleRecurringAppointmentAction::class),
        );
    }

    public function create(AppointmentData $data): Appointment
    {
        return $this->container->make(CreateAppointmentAction::class)->execute($data);
    }

    public function reschedule(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        ?int $durationMinutes = null,
        bool $preventConflicts = false,
    ): Appointment {
        return $this->container->make(RescheduleAppointmentAction::class)
            ->execute($appointment, $startsAt, $durationMinutes, $preventConflicts);
    }

    public function transition(Appointment $appointment, Status $to): Appointment
    {
        return $this->container->make(TransitionAppointmentAction::class)->execute($appointment, $to);
    }
}
