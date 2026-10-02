<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * Dispatched once the surrounding database transaction commits (immediately outside one), so a
 * write that rolls back never announces itself.
 */
final class AppointmentRescheduled implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Appointment $appointment,
        public CarbonInterface $previousStartsAt,
    ) {}
}
