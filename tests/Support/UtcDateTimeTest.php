<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Casts\UtcDateTime;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\DefaultTimezone;

beforeEach(function (): void {
    $this->cast = new UtcDateTime;
    $this->model = new Appointment;
});

afterEach(function (): void {
    date_default_timezone_set('UTC');
});

it('reads a stored value as UTC whatever the PHP default zone is', function (): void {
    date_default_timezone_set('Asia/Tokyo');

    $read = $this->cast->get($this->model, 'starts_at', '2026-07-01 15:30:00', []);

    expect($read?->format('Y-m-d H:i:s e'))->toBe('2026-07-01 15:30:00 UTC');
});

it('reads values a driver renders differently', function (mixed $stored, string $expected): void {
    expect($this->cast->get($this->model, 'starts_at', $stored, [])?->format('Y-m-d H:i:s e'))->toBe($expected);
})->with([
    'fractional seconds' => ['2026-07-01 15:30:00.000000', '2026-07-01 15:30:00 UTC'],
    'with an offset' => ['2026-07-01 17:30:00+02:00', '2026-07-01 15:30:00 UTC'],
    'a timestamp' => [1782919800, '2026-07-01 15:30:00 UTC'],
    'a DateTime' => [new DateTimeImmutable('2026-07-01 17:30:00', new DateTimeZone('Europe/Bratislava')), '2026-07-01 15:30:00 UTC'],
]);

it('reads nothing as null', function (mixed $stored): void {
    expect($this->cast->get($this->model, 'starts_at', $stored, []))->toBeNull();
})->with([null, '']);

it('writes any moment as its UTC wall-clock', function (mixed $value): void {
    expect($this->cast->set($this->model, 'starts_at', $value, []))->toBe('2026-07-01 15:30:00');
})->with([
    'a zoned Carbon' => [CarbonImmutable::parse('2026-07-01 17:30', 'Europe/Bratislava')],
    'a string with an offset' => ['2026-07-01T17:30:00+02:00'],
    'a timestamp' => [1782919800],
]);

it('reads a naked string in the app timezone', function (): void {
    date_default_timezone_set('Europe/Bratislava');

    expect($this->cast->set($this->model, 'starts_at', '2026-07-01 17:30', []))->toBe('2026-07-01 15:30:00');
});

it('writes nothing as null', function (mixed $value): void {
    expect($this->cast->set($this->model, 'starts_at', $value, []))->toBeNull();
})->with([null, '']);

it('falls back to UTC when no timezone is configured anywhere', function (): void {
    config()->set('appointments.timezone', '');
    config()->set('app.timezone', null);

    expect(DefaultTimezone::resolve())->toBe('UTC');
});
