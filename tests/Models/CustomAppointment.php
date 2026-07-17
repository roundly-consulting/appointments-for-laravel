<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host model swapped in via `appointments.model`.
 *
 * `CountsCreations` is required by `toHonourModelSwap`, not optional, and it is the whole
 * proof: `instanceof` passes even when a package helper created the row as the PACKAGED
 * class (permissions #31), because re-querying through the host class re-hydrates the row
 * whatever it was created as. Counting `created` events on this exact class is the
 * independent oracle. Without the trait the assertion used to silently drop that half.
 */
class CustomAppointment extends Appointment
{
    use CountsCreations;

    protected $table = 'appointments';

    public function isHostModel(): bool
    {
        return true;
    }
}
