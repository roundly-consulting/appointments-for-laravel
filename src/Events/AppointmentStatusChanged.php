<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * Dispatched once the surrounding database transaction commits (immediately outside one), so a
 * write that rolls back never announces itself.
 */
final class AppointmentStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Appointment $appointment,
        public Status $from,
        public Status $to,
    ) {}
}
