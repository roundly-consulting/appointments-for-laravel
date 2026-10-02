<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * An instant stored as its UTC wall-clock and read back as a UTC CarbonImmutable — whatever
 * `app.timezone` is.
 *
 * Eloquent's own datetime casts write a Carbon's wall-clock as-is and read the column back in
 * `app.timezone`, so an app not running in UTC shifted every appointment by its offset, and a
 * Carbon in another zone was stored as the wrong instant. This cast converts on the way in and
 * pins UTC on the way out, so the column always means one thing.
 *
 * A string assigned to the attribute is read like any Eloquent date: in `app.timezone` unless it
 * carries its own offset.
 *
 * @internal the cast behind `Appointment::$starts_at` / `$ends_at`
 *
 * @implements CastsAttributes<CarbonImmutable, DateTimeInterface|string|int>
 */
final class UtcDateTime implements CastsAttributes
{
    /** Always re-read from the stored value, so the attribute never hands back a non-UTC Carbon. */
    public bool $withoutObjectCaching = true;

    /**
     * The given moment as a UTC CarbonImmutable — a Carbon/DateTime keeps its instant, a
     * timestamp is seconds since the epoch, and a string is parsed in `app.timezone` unless it
     * names its own offset.
     */
    public static function toUtc(DateTimeInterface|string|int $value): CarbonImmutable
    {
        return match (true) {
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->utc(),
            is_int($value) => CarbonImmutable::createFromTimestamp($value, 'UTC'),
            default => CarbonImmutable::parse($value)->utc(),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value, 'UTC');
        }

        /** @var string $value */
        try {
            $parsed = CarbonImmutable::createFromFormat($model->getDateFormat(), $value, 'UTC');
        } catch (InvalidArgumentException) {
            $parsed = null;
        }

        // A driver that renders the column differently (fractional seconds, an offset) still
        // parses; a bare value is UTC by definition of the column.
        return $parsed instanceof CarbonImmutable ? $parsed : CarbonImmutable::parse($value, 'UTC')->utc();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        /** @var DateTimeInterface|string|int $value */
        return self::toUtc($value)->format($model->getDateFormat());
    }
}
