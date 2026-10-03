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
        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure reads a dozen
        // `appointments.*` keys for real, and the toolkit's `bindFromConfig()` reads more
        // still. Excluding it would discard the only reader of most of this file.
    ]);
});
