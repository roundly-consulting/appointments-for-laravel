<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Enums;

use RoundlyConsulting\Enums\Helpers;

enum ParticipantRole: string
{
    use Helpers;

    case Organiser = 'organiser';
    case Attendee = 'attendee';
    case Optional = 'optional';

    public static function default(): self
    {
        return self::Attendee;
    }
}
