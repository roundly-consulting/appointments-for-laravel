<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Enums;

enum Status: string
{
    case New = 'New';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
    case Canceled = 'Canceled';
}
