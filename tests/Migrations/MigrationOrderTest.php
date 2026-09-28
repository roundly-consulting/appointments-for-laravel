<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Appointments\AppointmentsServiceProvider;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

$migrations = __DIR__.'/../../database/migrations';

/**
 * M — the structural pin, and a RE-SCORE of the row spec, which said appointments skips it.
 *
 * The spec read `M` as "packages with FK edges", and appointments declares none. But
 * `MigrationGraph::assertRunnable()` checks two INDEPENDENT things and only one is
 * FK-related: the other pins that a `Schema::table()` ALTER sorts at or after the CREATE of
 * the table it alters. That is approvals #2 — the bug that half was built for — and
 * appointments has exactly its shape: `0003_add_location_to_appointments_table` alters
 * `appointments`, created in `0001`.
 *
 * `foreignKeys: 0` is a live pin, not a formality: it fails the moment an FK arrives without
 * this count being deliberately updated, so the edge can never be added unnoticed.
 */
it('sorts every ALTER after the CREATE of the table it alters', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(
        foreignKeys: 0,
        // Appointments' table names are configurable, so each migration resolves its own
        // through a private helper rather than a string literal. The resolver never guesses
        // on a non-literal — an unmapped expression FAILS rather than silently dropping the
        // table, which is what keeps `foreignKeys: 0` honest instead of a number that passes
        // over an empty parse.
        //
        // The helpers are named per TABLE (appointmentsTable/participantsTable) rather than a
        // single `table()`, and that is load-bearing here rather than cosmetic: this map is
        // keyed on the raw expression TEXT, so three files all saying `$this->table()` while
        // meaning two different tables cannot be expressed — one key would have to resolve to
        // both. Distinct expressions per table is also how permissions solved the same
        // problem (PermissionRegistrar::rolesTable() etc.).
        tableResolvers: [
            '$this->appointmentsTable()' => 'appointments',
            '$this->participantsTable()' => 'appointment_participants',
        ],
    );
});

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `3` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(AppointmentsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(AppointmentsServiceProvider::class)->toPublishMigrationsTimestamped('appointments-migrations', 3);
});

/**
 * R — the real-engine proof, `toApplyOnConnection` only.
 *
 * `toRejectBrokenOrderOnConnection` is deliberately NOT adopted: it reverses the migration
 * list, and with no FK edges Postgres has nothing to refuse — it would accept the reversed
 * set and the assertion would fail by design. (The ALTER would fail, but the negative
 * control asserts on the FK refusal, not on any error.) That is the check working correctly
 * against a shape it does not fit.
 *
 * `migrations: 3` pins the file count, and the runner independently fails a set that
 * "applies cleanly" while creating no tables — an empty `up()` otherwise proves nothing.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 3);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the env-DECLARED driver against what the connection
 * itself answers, so a "pgsql" leg that quietly stayed on SQLite — a decapitated
 * `defineEnvironment()`, a missing `TESTING_DB_DRIVER` — goes red here rather than passing as
 * a postgres run. It fires automatically, unlike reading a skip count by hand.
 */
it('runs on the driver the environment declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The `meta` jsonb column and the decimal lat/long added by the ALTER are what the drivers
 * render differently. Pinning a round-trip on whatever engine the leg configured proves the
 * columns are usable rather than merely creatable.
 *
 * `meta` is asserted key-by-key rather than as a whole array: jsonb sorts object keys by
 * (length, bytes), so a whole-array `toBe` would pin a storage order Postgres never
 * promised. Each key keeps a strict `toBe` rather than relaxing to `toEqual` — this test
 * exists to prove the driver renders the column faithfully, and `toEqual` (which is `==`)
 * would let the int 2 come back as the string "2".
 */
it('round-trips the appointment columns on the configured engine', function (): void {
    $appointment = Appointments::schedule('Kickoff')
        ->startingAt('2026-08-01 17:30')
        ->lasting(90)
        ->withMeta(['region' => 'eu', 'tier' => 2])
        ->located(48.1486, 17.1077, 'Bratislava')
        ->create();

    $fresh = $appointment->fresh();

    expect($fresh->meta['region'] ?? null)->toBe('eu')
        ->and($fresh->meta['tier'] ?? null)->toBe(2)
        ->and($fresh->location)->toBe('Bratislava')
        ->and((float) $fresh->latitude)->toBe(48.1486)
        ->and((float) $fresh->longitude)->toBe(17.1077)
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
