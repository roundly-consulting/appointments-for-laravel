<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentStatusChanged;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Models\Appointment;

final class TransitionAppointmentAction
{
    public function execute(Appointment $appointment, Status $to): Appointment
    {
        $from = $appointment->status;

        if ($from === $to) {
            return $appointment;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransitionException::make($from, $to);
        }

        $appointment->status = $to;
        $appointment->save();

        Event::dispatch(new AppointmentStatusChanged($appointment, $from, $to));

        return $appointment;
    }
}
