<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use RoundlyConsulting\Appointments\Models\Appointment;

abstract class AppointmentEvent
{
    public function __construct(public Appointment $appointment) {}
}
