<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use Carbon\CarbonInterface;
use RoundlyConsulting\Appointments\Models\Appointment;

final class AppointmentRescheduled
{
    public function __construct(
        public Appointment $appointment,
        public CarbonInterface $previousStartsAt,
    ) {}
}
