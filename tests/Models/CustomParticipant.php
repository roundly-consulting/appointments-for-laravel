<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use RoundlyConsulting\Appointments\Models\Participant;

/**
 * A host model swapped in via `appointments.participant`.
 */
class CustomParticipant extends Participant
{
    public function isHostModel(): bool
    {
        return true;
    }
}
