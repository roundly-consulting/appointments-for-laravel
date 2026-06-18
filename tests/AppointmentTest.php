<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\AppointmentUpdated;
use RoundlyConsulting\Appointments\Models\Appointment;

it('casts status to enum', function (): void {
    $appointment = Appointment::factory()->create();

    expect($appointment->status)->toBeInstanceOf(Status::class);
});

it('casts starts_at and ends_at to immutable Carbon instances', function (): void {
    $appointment = Appointment::factory()->create();

    expect($appointment->starts_at)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($appointment->ends_at)
        ->toBeInstanceOf(CarbonImmutable::class);
});

it('casts meta data as collection', function (): void {
    $appointment = Appointment::factory()->create([
        'meta' => collect([
            'something' => 'okay',
            'nothings' => 'fine',
        ]),
    ]);

    expect($appointment->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()
        ->toBe([
            'something' => 'okay',
            'nothings' => 'fine',
        ]);
});

it('dispatches AppointmentCreated event', function (): void {
    Event::fake();

    $appointment = Appointment::factory()->create();

    Event::assertDispatched(AppointmentCreated::class, fn (AppointmentCreated $e) => $e->appointment->is($appointment));
});

it('dispatches AppointmentUpdated event', function (): void {
    Event::fake();

    $appointment = Appointment::factory()->create();
    $appointment->update(['meta' => null]);

    Event::assertDispatched(AppointmentUpdated::class, fn (AppointmentUpdated $e) => $e->appointment->is($appointment));
});

it('has relationship to participants', function (): void {
    $appointment = Appointment::factory()->create();

    expect($appointment->participants())->toBeInstanceOf(HasMany::class);
});

it('defaults status to pending when omitted', function (): void {
    $appointment = Appointment::create([
        'name' => 'Standup',
        'starts_at' => now(),
    ]);

    expect($appointment->fresh()->status)->toBe(Status::Pending);
});

it('derives ends_at from duration on save', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'duration_minutes' => 90,
        'ends_at' => null,
    ]);

    expect($appointment->fresh()->ends_at->format('Y-m-d H:i'))->toBe('2026-07-01 10:30');
});

it('keeps an explicit ends_at and back-fills duration', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'ends_at' => Carbon::parse('2026-07-01 11:00'),
        'duration_minutes' => null,
    ]);

    expect($appointment->fresh())
        ->ends_at->format('Y-m-d H:i')->toBe('2026-07-01 11:00')
        ->duration_minutes->toBe(120);
});

it('falls back to the configured default duration', function (): void {
    config()->set('appointments.default_duration_minutes', 45);

    $appointment = Appointment::create([
        'name' => 'Quick sync',
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
    ]);

    expect($appointment->fresh())
        ->duration_minutes->toBe(45)
        ->ends_at->format('Y-m-d H:i')->toBe('2026-07-01 09:45');
});

it('exposes a CarbonInterval duration', function (): void {
    $appointment = Appointment::factory()->create(['duration_minutes' => 60]);

    expect($appointment->duration())
        ->toBeInstanceOf(CarbonInterval::class)
        ->and($appointment->durationInMinutes())->toBe(60);
});

it('computes duration from ends_at when duration_minutes is absent', function (): void {
    $appointment = Appointment::factory()->make([
        'starts_at' => Carbon::parse('2026-07-01 09:00'),
        'ends_at' => Carbon::parse('2026-07-01 09:30'),
        'duration_minutes' => null,
    ]);

    expect($appointment->durationInMinutes())->toBe(30);
});

it('falls back to the config default for durationInMinutes when nothing is set', function (): void {
    config()->set('appointments.default_duration_minutes', 75);

    $appointment = Appointment::factory()->make([
        'duration_minutes' => null,
        'ends_at' => null,
    ]);

    expect($appointment->durationInMinutes())->toBe(75);
});

it('resolves timezone from the appointment, then config, then app', function (): void {
    $explicit = Appointment::factory()->make(['timezone' => 'Europe/Bratislava']);
    expect($explicit->resolveTimezone())->toBe('Europe/Bratislava');

    config()->set('appointments.timezone', 'America/New_York');
    $fromConfig = Appointment::factory()->make(['timezone' => null]);
    expect($fromConfig->resolveTimezone())->toBe('America/New_York');

    config()->set('appointments.timezone', null);
    config()->set('app.timezone', 'UTC');
    $fromApp = Appointment::factory()->make(['timezone' => null]);
    expect($fromApp->resolveTimezone())->toBe('UTC');
});

it('returns local start and end times in the appointment timezone', function (): void {
    $appointment = Appointment::factory()->create([
        'timezone' => 'Europe/Bratislava',
        'starts_at' => CarbonImmutable::parse('2026-07-01 08:00', 'UTC'),
        'duration_minutes' => 60,
        'ends_at' => null,
    ]);

    $appointment->refresh();

    expect($appointment->startsAtLocal()->format('Y-m-d H:i'))->toBe('2026-07-01 10:00')
        ->and($appointment->endsAtLocal()->format('Y-m-d H:i'))->toBe('2026-07-01 11:00');
});

it('returns null local end time when ends_at is null', function (): void {
    $appointment = Appointment::factory()->make([
        'ends_at' => null,
    ]);

    expect($appointment->endsAtLocal())->toBeNull();
});

it('soft deletes an appointment', function (): void {
    $appointment = Appointment::factory()->create();

    $appointment->delete();

    expect($appointment->trashed())->toBeTrue()
        ->and(Appointment::withTrashed()->find($appointment->getKey()))->not->toBeNull()
        ->and(Appointment::find($appointment->getKey()))->toBeNull();
});
