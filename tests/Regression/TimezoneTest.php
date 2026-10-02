<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

/*
 * starts_at / ends_at hold UTC wall-clock values. Every way in — the builder, a DTO, a model
 * attribute — and every way out — the model cast, the scopes, the conflict checks, the ICS export
 * — must agree on that, whatever `app.timezone` is and whatever zone a Carbon argument carries.
 */

function useAppTimezone(string $timezone): void
{
    config()->set('app.timezone', $timezone);
    date_default_timezone_set($timezone);
}

afterEach(function (): void {
    useAppTimezone('UTC');
    Carbon::setTestNow();
});

/** The stored column value, read straight from the table. */
function rawStartsAt(Appointment $appointment): string
{
    /** @var string $value */
    $value = DB::table($appointment->getTable())->where('id', $appointment->getKey())->value('starts_at');

    return substr($value, 0, 19);
}

it('compares non-UTC Carbon arguments as the instants they are', function (): void {
    $host = User::create();

    $appointment = Appointments::schedule('Booked')
        ->startingAt('2026-07-10 17:30', timezone: 'Europe/Bratislava')
        ->lasting(60)
        ->withParticipant($host)
        ->create();

    $start = CarbonImmutable::parse('2026-07-10 17:30', 'Europe/Bratislava');
    $end = $start->addHour();

    expect(rawStartsAt($appointment))->toBe('2026-07-10 15:30:00')
        ->and(Appointments::isAvailable($host, $start, $end))->toBeFalse()
        ->and(Appointments::conflicts($host, $start, $end))->toHaveCount(1)
        ->and(Appointments::isAvailable($host, $appointment->startsAtLocal(), $appointment->endsAtLocal()))->toBeFalse()
        ->and(Appointment::query()->overlapping($start, $end)->count())->toBe(1)
        ->and(Appointment::query()->between($start->subMinutes(5), $start->addMinutes(5))->count())->toBe(1)
        // 17:00–17:29 Bratislava is 15:00–15:29 UTC: free.
        ->and(Appointments::isAvailable($host, $start->subMinutes(30), $start))->toBeTrue();
});

it('round-trips the same instant when app.timezone is not UTC', function (): void {
    useAppTimezone('Europe/Bratislava');
    $host = User::create();

    $appointment = Appointments::schedule('Booked')
        ->startingAt('2026-07-01 17:30', timezone: 'Europe/Bratislava')
        ->lasting(60)
        ->withParticipant($host)
        ->create();

    $fresh = $appointment->fresh();
    $local = CarbonImmutable::parse('2026-07-01 17:30', 'Europe/Bratislava');

    expect(rawStartsAt($appointment))->toBe('2026-07-01 15:30:00')
        ->and($fresh->starts_at->equalTo($local))->toBeTrue()
        ->and($fresh->ends_at->equalTo($local->addHour()))->toBeTrue()
        ->and($fresh->startsAtLocal()->format('Y-m-d H:i'))->toBe('2026-07-01 17:30')
        ->and($fresh->toIcs())->toContain('DTSTART:20260701T153000Z')
        ->and(Appointments::isAvailable($host, $local, $local->addHour()))->toBeFalse();

    Carbon::setTestNow(CarbonImmutable::parse('2026-07-01 16:45', 'Europe/Bratislava'));

    expect(Appointment::query()->upcoming()->count())->toBe(1)
        ->and(Appointment::query()->past()->count())->toBe(0);

    Carbon::setTestNow(CarbonImmutable::parse('2026-07-01 17:31', 'Europe/Bratislava'));

    expect(Appointment::query()->upcoming()->count())->toBe(0)
        ->and(Appointment::query()->past()->count())->toBe(1);
});

it('keeps the instant through a reschedule when app.timezone is not UTC', function (): void {
    useAppTimezone('America/New_York');

    $appointment = Appointments::schedule('Moved')->startingAt('2026-07-01 09:00', timezone: 'UTC')->create();

    Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-02 18:00', 'Europe/Bratislava'));

    expect(rawStartsAt($appointment))->toBe('2026-07-02 16:00:00')
        ->and($appointment->fresh()->starts_at->equalTo(CarbonImmutable::parse('2026-07-02 16:00', 'UTC')))->toBeTrue();
});

it('reads naked wall-clock input in appointments.timezone and stores that zone', function (): void {
    config()->set('appointments.timezone', 'Europe/Bratislava');

    $appointment = Appointments::schedule('Local')->startingAt('2026-07-01 17:30')->until('2026-07-01 18:15')->create();

    expect($appointment->timezone)->toBe('Europe/Bratislava')
        ->and(rawStartsAt($appointment))->toBe('2026-07-01 15:30:00')
        ->and($appointment->startsAtLocal()->format('H:i'))->toBe('17:30')
        ->and($appointment->durationInMinutes())->toBe(45);

    // The zone is stored with the booking, so a later config change does not re-time it.
    config()->set('appointments.timezone', 'America/New_York');

    expect($appointment->fresh()->startsAtLocal()->format('H:i'))->toBe('17:30');
});

it('stores the app timezone when neither the booking nor the config names one', function (): void {
    useAppTimezone('Europe/Bratislava');

    $appointment = Appointments::schedule('Default')->startingAt('2026-07-01 17:30')->create();

    expect($appointment->timezone)->toBe('Europe/Bratislava')
        ->and(rawStartsAt($appointment))->toBe('2026-07-01 15:30:00');
});

it('keeps a weekly series on the same local time across a DST change', function (): void {
    config()->set('appointments.timezone', 'Europe/Bratislava');

    // 2026-10-25 is the CEST → CET switch.
    $series = Appointments::schedule('Standup')
        ->startingAt('2026-10-19 09:00')
        ->recurring(new RecurrenceData(Frequency::Weekly, count: 3))
        ->createRecurring();

    expect($series->map(fn (Appointment $a): string => $a->startsAtLocal()->format('Y-m-d H:i'))->all())
        ->toBe(['2026-10-19 09:00', '2026-10-26 09:00', '2026-11-02 09:00'])
        ->and($series->map(fn (Appointment $a): string => rawStartsAt($a))->all())
        ->toBe(['2026-10-19 07:00:00', '2026-10-26 08:00:00', '2026-11-02 08:00:00']);
});

it('expands a DTO series in the appointment timezone, not the zone of its start', function (): void {
    $series = Appointments::createRecurring(new AppointmentData(
        name: 'Standup',
        startsAt: CarbonImmutable::parse('2026-10-19 07:00', 'UTC'),
        timezone: 'Europe/Bratislava',
    ), new RecurrenceData(Frequency::Weekly, count: 2));

    expect($series->map(fn (Appointment $a): string => $a->startsAtLocal()->format('Y-m-d H:i'))->all())
        ->toBe(['2026-10-19 09:00', '2026-10-26 09:00']);
});

it('converts a non-UTC Carbon assigned straight to the model', function (): void {
    useAppTimezone('Europe/Bratislava');

    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 12:00', 'Asia/Tokyo'),
    ]);

    expect(rawStartsAt($appointment))->toBe('2026-07-01 03:00:00')
        ->and($appointment->fresh()->starts_at->equalTo(CarbonImmutable::parse('2026-07-01 03:00', 'UTC')))->toBeTrue();
});
