<?php

declare(strict_types=1);

/**
 * C — the config-key contract, pinned in both directions.
 *
 * Reads are scraped from source **tokens**, never a regex — media #27's near-miss was a
 * regex over raw text satisfied by a *docblock mention* of the key, which stayed green with
 * the fix reverted. A docblock is a comment token here, never a read.
 *
 *  - forward — every key the code reads is shipped (shops #18);
 *  - reverse — every shipped leaf is read (alerts #24; media #27's cap that never applied).
 *
 * Adopting it found `config("appointments.table_names.{$key}")` in the provider — an
 * interpolated key that could not be checked against the shipped file at all. Both table
 * names are leaves of a flat map and every caller already passed a literal, so the
 * interpolation bought nothing; an exhaustive `match` now names each key, following
 * cosmos-foundation's precedent for the same shape.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/appointments.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // The two model keys are read through the toolkit's `ModelResolver::for('appointments.…')`
        // seam rather than a `config()` call. They are real reads — they drive the whole
        // swap — but they are not `config(` tokens, so a prefix is what makes them visible to
        // the scraper.
        //
        // The keys are named exactly rather than using a blanket `'appointments.'`, which
        // would count ANY string literal under the prefix as a read wherever it appeared —
        // including translation keys and table names that are not config keys at all (the
        // trap alerts hit with its `alerts.health` route-name default).
        'extraReadPrefixes' => [
            'appointments.model',
            'appointments.participant',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure reads a dozen
        // `appointments.*` keys for real, and the toolkit's `bindFromConfig()` reads more
        // still. Excluding it would discard the only reader of most of this file.
    ]);
});
