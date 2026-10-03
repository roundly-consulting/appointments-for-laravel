<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

/**
 * The zone an appointment gets when none is named: `appointments.timezone` (a malformed one
 * throws, naming the key), else `app.timezone`, else UTC. Naked wall-clock input is read in it, and a new appointment stores
 * it, so its local time stays what was booked even if the config changes later.
 *
 * @internal
 */
final class DefaultTimezone
{
    public static function resolve(): string
    {
        $configured = AppointmentsConfig::timezone();

        if ($configured !== null) {
            return $configured;
        }

        $app = config('app.timezone');

        return is_string($app) && $app !== '' ? $app : 'UTC';
    }
}
