<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Enums;

use RoundlyConsulting\Enums\Helpers;

enum Frequency: string
{
    use Helpers;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
