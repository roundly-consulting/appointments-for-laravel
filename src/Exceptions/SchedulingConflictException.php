<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Models\Appointment;

final class SchedulingConflictException extends AppointmentsException
{
    /**
     * @param  Collection<int, Appointment>  $conflicts
     */
    public function __construct(
        public readonly Collection $conflicts,
    ) {
        parent::__construct(
            sprintf('The requested time conflicts with %d existing appointment(s).', $conflicts->count()),
        );
    }

    /**
     * @param  Collection<int, Appointment>  $conflicts
     */
    public static function make(Collection $conflicts): self
    {
        return new self($conflicts);
    }
}
