<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use RoundlyConsulting\Appointments\Models\Participant;

/**
 * Dispatched once the surrounding database transaction commits (immediately outside one), so a
 * write that rolls back never announces itself.
 */
abstract class ParticipantEvent implements ShouldDispatchAfterCommit
{
    public function __construct(public Participant $participant) {}
}
