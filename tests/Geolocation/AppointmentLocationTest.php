<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

it('round-trips coordinates through the cast', function (): void {
    $appointment = Appointments::for('Clinic visit')
        ->startingAt('2026-07-01 09:00')
        ->located(51.5074, -0.1278, 'Clinic A')
        ->create();

    $fresh = $appointment->fresh();

    expect($fresh?->location)->toBe('Clinic A')
        ->and($fresh?->latitude)->toBe(51.5074)
        ->and($fresh?->longitude)->toBe(-0.1278)
        ->and($fresh?->coordinates)->toBeInstanceOf(Coordinates::class)
        ->and($fresh?->coordinates?->latitude)->toBe(51.5074);
});

it('accepts a Coordinates value object via at()', function (): void {
    $appointment = Appointments::for('Remote')
        ->startingAt('2026-07-01 09:00')
        ->at(new Coordinates(40.7128, -74.0060))
        ->create();

    expect($appointment->coordinates?->longitude)->toBe(-74.0060);
});

it('filters appointments within a radius', function (): void {
    Appointments::for('London')->startingAt('2026-07-01 09:00')->located(51.5074, -0.1278)->create();
    Appointments::for('Paris')->startingAt('2026-07-01 09:00')->located(48.8566, 2.3522)->create();

    $near = Appointment::query()
        ->withinRadius(new Coordinates(51.5074, -0.1278), 50)
        ->pluck('name');

    expect($near)->toContain('London')->not->toContain('Paris');
});

it('measures distance from a point in kilometres', function (): void {
    $appointment = Appointments::for('London')->startingAt('2026-07-01 09:00')->located(51.5074, -0.1278)->create();

    $distance = $appointment->distanceFrom(new Coordinates(48.8566, 2.3522));

    expect($distance)->toBeGreaterThan(330.0)->toBeLessThan(360.0);
});

it('returns null distance when the appointment has no coordinates', function (): void {
    $appointment = Appointments::for('Undisclosed')->startingAt('2026-07-01 09:00')->create();

    expect($appointment->distanceFrom(new Coordinates(0.0, 0.0)))->toBeNull()
        ->and($appointment->coordinates)->toBeNull();
});
