<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * Dispatched once the surrounding database transaction commits (immediately outside one), so a
 * write that rolls back never announces itself.
 */
abstract class AppointmentEvent implements ShouldDispatchAfterCommit
{
    public function __construct(public Appointment $appointment) {}
}
