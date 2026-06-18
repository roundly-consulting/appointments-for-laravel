<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

final class AppointmentStatusChanged
{
    public function __construct(
        public Appointment $appointment,
        public Status $from,
        public Status $to,
    ) {}
}
