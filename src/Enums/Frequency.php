<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Enums;

enum Frequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
