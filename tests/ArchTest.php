<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Events\AppointmentEvent;
use RoundlyConsulting\Appointments\Events\ParticipantEvent;
use RoundlyConsulting\Appointments\Exceptions\AppointmentsException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Appointments shipped **no arch test at all** — this whole file is new coverage, which is
 * the jwt shape (its bug #4, `final` on a swappable model, existed precisely because nothing
 * was looking).
 */
ArchPresets::strictTypes('RoundlyConsulting\Appointments');

/**
 * The exemptions, each a real extension point rather than an oversight:
 *
 *  - Appointment / Participant, the two models `config/appointments.php` invites a host to
 *    swap — pinned instead by the preset below, the deliberate tension the two presets hold;
 *  - AppointmentsException, the exception base hosts catch;
 *  - AppointmentEvent / ParticipantEvent, the abstract event bases the concrete
 *    created/updated/deleted events extend (and the concrete events themselves, which are
 *    left open so a host swapping a model can carry its own).
 */
ArchPresets::finalByDefault('RoundlyConsulting\Appointments')
    ->ignoring([
        Appointment::class,
        Participant::class,
        AppointmentsException::class,
        AppointmentEvent::class,
        ParticipantEvent::class,
        'RoundlyConsulting\Appointments\Events\AppointmentCreated',
        'RoundlyConsulting\Appointments\Events\AppointmentUpdated',
        'RoundlyConsulting\Appointments\Events\ParticipantCreated',
        'RoundlyConsulting\Appointments\Events\ParticipantUpdated',
        'RoundlyConsulting\Appointments\Events\ParticipantDeleted',
    ]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable model
 * is a PHP fatal the moment a host uses the seam the config documents. Both models are
 * correctly non-final today; verified, not assumed. The preset also pins that each key
 * really defaults to the packaged model, so the seam cannot rot in the other direction.
 *
 * Swap is TWO, not the three the row spec listed:
 * `appointments.reviews.verified_attendance_resolver` binds a
 * VerifiedAttendanceResolver implementation — a strategy object, not an Eloquent model — so
 * it is not an `S` seam and `toHonourModelSwap` structurally cannot apply to it. (Same
 * distinction that re-scored metrics' "4" and kubernetes-api's "11".)
 */
ArchPresets::swappableModelsAreNotFinal([
    Appointment::class => 'appointments.model',
    Participant::class => 'appointments.participant',
]);

/**
 * Appointments does no cryptography. The ban is a standing guard against an ICS signature or
 * a booking token being hand-rolled here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Appointments');

/**
 * Adopted, not rejected: appointments has exactly the shape the preset targets — two real
 * Eloquent models behind config keys, read through a Support seam (`Support/AppointmentModel`
 * and `Support/ParticipantModel`, both wrapping `ModelResolver::for(…)`).
 *
 * `$modelKeys` is DECLARED rather than inferred, and here that is load-bearing rather than
 * belt-and-braces. The preset infers a swap key by SHAPE (a `model` / `models` / `*_model`
 * segment), so `appointments.model` is found and `appointments.participant` is NOT — it has
 * no `model` segment at all, exactly like alerts' `alerts.alert`. Undeclared, the
 * stray-literal half would cover one seam of two while looking authoritative. Declared keys
 * are unioned with the inferred ones, so naming them can only add coverage.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', [
    'appointments.model',
    'appointments.participant',
]);

/**
 * The Dependency Policy as a test. No `alsoAllow`: appointments' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`. If it goes red
 * the graph is wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
