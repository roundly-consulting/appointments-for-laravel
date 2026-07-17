<?php

declare(strict_types=1);

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`, so every "does not leak" check was vacuous. Appointments had NO about test
 * at all, so this is new coverage rather than a replacement.
 *
 * Appointments has no API key. The provider itself decides that table names are host schema
 * and render plainly, while the default timezone is a deployment detail reported as presence
 * only — this pins that decision rather than re-litigating it. `mustRender` is asserted
 * BEFORE any secret check and throws at call time if empty, so this cannot degrade into the
 * purchases shape.
 */
it('renders the appointments section reporting the timezone as presence only', function (): void {
    config()->set('appointments.timezone', 'Antarctica/Troll');
    config()->set('appointments.prevent_conflicts', true);
    config()->set('appointments.reviews.require_verified_attendance', true);
    config()->set('appointments.approvals.enforce_transitions', true);

    expect('appointments')->toLeakNoSecrets(
        secrets: [
            // A deployment detail the provider deliberately reports as presence, never value.
            'Antarctica/Troll',
        ],
        mustRender: [
            // The positive proof each line reports rather than being silently empty.
            'Appointment',
            'Participant model',
            // Table names render plainly BY DESIGN — host schema, not secrets.
            'appointments',
            'appointment_participants',
            'SET',
            'min',
            'ON',
        ],
    );
});

/**
 * The switches render as switches, and an unset timezone reports APP DEFAULT rather than an
 * empty string. Kept separate: it is a rendering pin, not a leak pin, and folding it into the
 * case above would need the opposite config.
 */
it('reports the configured models and switches in the about section', function (): void {
    config()->set('appointments.timezone', null);
    config()->set('appointments.prevent_conflicts', false);
    config()->set('appointments.reviews.require_verified_attendance', false);
    config()->set('appointments.approvals.enforce_transitions', false);

    expect('appointments')->toLeakNoSecrets(
        secrets: ['Antarctica/Troll'],
        mustRender: ['Appointment', 'Participant', 'APP DEFAULT', 'OFF'],
    );
});
