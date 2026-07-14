<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing appointment participants from
 * `appointments.participant`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not a Participant (so it cannot answer the
 * package's role cast or appointment relation) falls back to the packaged model.
 */
final class ParticipantModel
{
    /**
     * @return class-string<Participant>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('appointments.participant', Participant::class);

        return is_a($model, Participant::class, true) ? $model : Participant::class;
    }
}
