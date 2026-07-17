<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host model swapped in via `appointments.participant`. See {@see CustomAppointment} for
 * why CountsCreations is mandatory rather than decorative.
 */
class CustomParticipant extends Participant
{
    use CountsCreations;

    protected $table = 'appointment_participants';

    public function isHostModel(): bool
    {
        return true;
    }
}
