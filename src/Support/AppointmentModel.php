<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing appointments from `appointments.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class AppointmentModel
{
    /**
     * @return class-string<Appointment>
     */
    public static function class(): string
    {
        return ModelResolver::for('appointments.model', Appointment::class);
    }
}
