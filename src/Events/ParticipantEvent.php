<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Events;

use RoundlyConsulting\Appointments\Models\Participant;

abstract class ParticipantEvent
{
    public function __construct(public Participant $participant) {}
}
