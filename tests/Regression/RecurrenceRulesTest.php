<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Exceptions\InvalidRecurrenceException;
use RoundlyConsulting\Appointments\Facades\Appointments;

/**
 * @return list<string>
 */
function occurrenceDates(string $start, RecurrenceData $rule): array
{
    return array_map(
        static fn (CarbonImmutable $occurrence): string => $occurrence->format('Y-m-d'),
        Appointments::occurrences(CarbonImmutable::parse($start), $rule),
    );
}

// 2026-07-06 is a Monday.

it('keeps the weekly interval when a weekday filter is set', function (): void {
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Weekly, interval: 2, count: 4, byWeekday: [1])))
        ->toBe(['2026-07-06', '2026-07-20', '2026-08-03', '2026-08-17']);
});

it('repeats every listed weekday of each active week', function (): void {
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Weekly, interval: 2, count: 4, byWeekday: [1, 3])))
        ->toBe(['2026-07-06', '2026-07-08', '2026-07-20', '2026-07-22']);
});

it('counts weeks from the week of the start, not from the start day', function (): void {
    // Starts on a Wednesday: Friday of the same week is week 0, the next Monday is week 1 (skipped).
    expect(occurrenceDates('2026-07-08 09:00', new RecurrenceData(Frequency::Weekly, interval: 2, count: 3, byWeekday: [1, 5])))
        ->toBe(['2026-07-10', '2026-07-20', '2026-07-24']);
});

it('keeps the monthly interval when a weekday filter is set', function (): void {
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Monthly, interval: 2, count: 6, byWeekday: [1])))
        ->toBe(['2026-07-06', '2026-07-13', '2026-07-20', '2026-07-27', '2026-09-07', '2026-09-14']);
});

it('keeps the daily interval when a weekday filter is set', function (): void {
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Daily, interval: 2, count: 4, byWeekday: [1, 2, 3, 4, 5])))
        ->toBe(['2026-07-06', '2026-07-08', '2026-07-10', '2026-07-14']);
});

it('treats a date-only until as inclusive of that day', function (): void {
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Daily, until: CarbonImmutable::parse('2026-07-08'))))
        ->toBe(['2026-07-06', '2026-07-07', '2026-07-08']);
});

it('still stops at an until that carries a time', function (): void {
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Daily, until: CarbonImmutable::parse('2026-07-08 08:59'))))
        ->toBe(['2026-07-06', '2026-07-07']);
});

it('refuses an interval below one', function (int $interval): void {
    new RecurrenceData(Frequency::Daily, interval: $interval, count: 3);
})->with([0, -1])->throws(InvalidRecurrenceException::class, 'interval');

it('refuses a count below one', function (int $count): void {
    new RecurrenceData(Frequency::Daily, count: $count);
})->with([0, -2])->throws(InvalidRecurrenceException::class, 'count');

it('refuses a weekday outside 1–7', function (int $weekday): void {
    new RecurrenceData(Frequency::Weekly, count: 3, byWeekday: [1, $weekday]);
})->with([0, 8])->throws(InvalidRecurrenceException::class, 'weekday');

it('ends a weekday rule that can never meet an active period instead of walking forever', function (): void {
    // Every 7th day from a Monday is always a Monday, never a Tuesday.
    expect(occurrenceDates('2026-07-06 09:00', new RecurrenceData(Frequency::Daily, interval: 7, count: 3, byWeekday: [2])))
        ->toBe([]);
});
