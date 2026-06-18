<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Exceptions;

use RoundlyConsulting\Appointments\Enums\Status;

final class InvalidStatusTransitionException extends AppointmentsException
{
    public function __construct(
        public readonly Status $from,
        public readonly Status $to,
    ) {
        parent::__construct(
            sprintf('Cannot transition appointment status from "%s" to "%s".', $from->value, $to->value),
        );
    }

    public static function make(Status $from, Status $to): self
    {
        return new self($from, $to);
    }
}
