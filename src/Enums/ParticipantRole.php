<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Enums;

enum ParticipantRole: string
{
    case Organiser = 'organiser';
    case Attendee = 'attendee';
    case Optional = 'optional';

    public static function default(): self
    {
        return self::Attendee;
    }

    public function label(): string
    {
        return (string) trans('appointments::roles.'.$this->value);
    }
}
