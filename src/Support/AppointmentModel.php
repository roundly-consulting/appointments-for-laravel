<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing appointments from `appointments.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not an Appointment (so it cannot answer the
 * package's scopes, status transitions or participant relation) falls back to
 * the packaged model.
 */
final class AppointmentModel
{
    /**
     * @return class-string<Appointment>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('appointments.model', Appointment::class);

        return is_a($model, Appointment::class, true) ? $model : Appointment::class;
    }
}
