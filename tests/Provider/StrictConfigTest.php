<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\AppointmentsServiceProvider;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/*
 | A typo in a host's config must fail loudly, never quietly become a default. The three
 | switches used to be read with a `(bool)` cast, so `'off'` and `'false'` switched them ON
 | and a typo such as `'disabled'` did too. They now go through the toolkit's strict
 | `Config::boolean()`.
 */

function appointmentsSwitch(string $key, bool $default = false): string
{
    return (new ReflectionMethod(AppointmentsServiceProvider::class, 'switch'))->invoke(null, $key, $default);
}

it('reports env-style switch strings as what they say', function (string $key): void {
    config()->set($key, 'off');
    expect(appointmentsSwitch($key))->toBe('OFF');

    config()->set($key, 'yes');
    expect(appointmentsSwitch($key))->toBe('ON');
})->with([
    'appointments.prevent_conflicts',
    'appointments.reviews.require_verified_attendance',
    'appointments.approvals.enforce_transitions',
]);

it('refuses a mistyped switch in the about section (strict config)', function (string $key): void {
    config()->set($key, 'disabled');

    expect(fn () => appointmentsSwitch($key))
        ->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a boolean");
})->with([
    'appointments.prevent_conflicts',
    'appointments.reviews.require_verified_attendance',
    'appointments.approvals.enforce_transitions',
]);

it('reads a string "false" attendance requirement as off', function (): void {
    config()->set('appointments.reviews.require_verified_attendance', 'false');

    $appointment = Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create();

    expect($appointment->review(User::create())->rating(3)->create()->verified)->toBeFalse();
});

it('refuses a mistyped attendance requirement (strict config)', function (): void {
    config()->set('appointments.reviews.require_verified_attendance', 'required');

    $appointment = Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create();

    expect(fn () => $appointment->review(User::create()))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [appointments.reviews.require_verified_attendance] must be a boolean');
});

it('refuses a mistyped transition guard when syncing an approval (strict config)', function (): void {
    config()->set('appointments.approvals.enforce_transitions', 'enforcing');

    $organiser = User::create();
    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    expect(fn () => Approvals::for($appointment)->as($organiser)->approve())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [appointments.approvals.enforce_transitions] must be a boolean');
});

it('refuses to migrate on an unrecognized key type (strict config)', function (): void {
    config()->set('appointments.key_type', 'nonsense');

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/0002_create_appointment_participants_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [appointments.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});
