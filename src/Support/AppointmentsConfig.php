<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use DateTimeZone;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use Throwable;

/**
 * The strict readers behind every appointments setting that is not a switch or a model.
 *
 * An absent (null) key means the documented default. A present value of the wrong shape
 * throws InvalidConfigurationException naming the key: a typo never falls back silently.
 * Before, `(int)` turned a default duration of `'an hour'` into 0, the recurrence cap reached
 * `min()` unvalidated, a blank table name reached SQL, and a non-string timezone quietly
 * became the app's.
 *
 * @internal
 */
final class AppointmentsConfig
{
    /**
     * Minutes in a year — the longest default duration accepted.
     */
    private const int MAX_DURATION_MINUTES = 525_600;

    /**
     * The ceiling on the recurrence cap: past it, the cap no longer protects anything.
     */
    private const int MAX_OCCURRENCES = 100_000;

    public static function appointmentsTable(): string
    {
        return config('appointments.table_names.appointments') === null
            ? 'appointments'
            : Config::requireString('appointments.table_names.appointments');
    }

    public static function participantsTable(): string
    {
        return config('appointments.table_names.participants') === null
            ? 'appointment_participants'
            : Config::requireString('appointments.table_names.participants');
    }

    /**
     * Minutes an appointment lasts when it names neither an end nor a duration: 1 to a year,
     * 60 when absent.
     */
    public static function defaultDurationMinutes(): int
    {
        return Config::integer('appointments.default_duration_minutes', 60, min: 1, max: self::MAX_DURATION_MINUTES);
    }

    /**
     * The most occurrences one recurrence expands to: 1–100000, 365 when absent.
     */
    public static function maxOccurrences(): int
    {
        return Config::integer('appointments.recurrence.max_occurrences', 365, min: 1, max: self::MAX_OCCURRENCES);
    }

    /**
     * The configured default timezone, or null when none is set (absent or an empty value),
     * in which case the app's applies. Anything else must be a timezone PHP knows.
     */
    public static function timezone(): ?string
    {
        $timezone = config('appointments.timezone');

        if ($timezone === null || (is_string($timezone) && trim($timezone) === '')) {
            return null;
        }

        if (is_string($timezone)) {
            try {
                return (new DateTimeZone($timezone))->getName();
            } catch (Throwable) {
                // Falls through to the throw below, naming the key.
            }
        }

        $given = match (true) {
            is_string($timezone) => $timezone,
            is_int($timezone), is_float($timezone), is_bool($timezone) => var_export($timezone, true),
            default => get_debug_type($timezone),
        };

        throw new InvalidConfigurationException("Configuration value [appointments.timezone] must be a timezone identifier such as Europe/Bratislava, [{$given}] given.");
    }
}
