<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * A host model swapped in via `appointments.model`.
 */
class CustomAppointment extends Appointment
{
    protected $table = 'appointments';

    public function isHostModel(): bool
    {
        return true;
    }
}
