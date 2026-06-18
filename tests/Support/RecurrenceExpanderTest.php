<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Support\RecurrenceExpander;

beforeEach(function (): void {
    $this->expander = app(RecurrenceExpander::class);
    $this->start = CarbonImmutable::parse('2026-07-06 09:00'); // a Monday
});

it('expands daily occurrences bounded by count', function (): void {
    $dates = collect($this->expander->expand($this->start, new RecurrenceData(Frequency::Daily, count: 3)))
        ->map(fn (CarbonImmutable $d) => $d->format('Y-m-d'));

    expect($dates->all())->toBe(['2026-07-06', '2026-07-07', '2026-07-08']);
});

it('expands weekly occurrences', function (): void {
    $dates = collect($this->expander->expand($this->start, new RecurrenceData(Frequency::Weekly, count: 3)))
        ->map(fn (CarbonImmutable $d) => $d->format('Y-m-d'));

    expect($dates->all())->toBe(['2026-07-06', '2026-07-13', '2026-07-20']);
});

it('expands monthly occurrences without overflow', function (): void {
    $start = CarbonImmutable::parse('2026-01-31 09:00');
    $dates = collect($this->expander->expand($start, new RecurrenceData(Frequency::Monthly, count: 3)))
        ->map(fn (CarbonImmutable $d) => $d->format('Y-m-d'));

    expect($dates->all())->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('honours an interval greater than one', function (): void {
    $dates = collect($this->expander->expand($this->start, new RecurrenceData(Frequency::Daily, interval: 2, count: 3)))
        ->map(fn (CarbonImmutable $d) => $d->format('Y-m-d'));

    expect($dates->all())->toBe(['2026-07-06', '2026-07-08', '2026-07-10']);
});

it('stops at the until boundary', function (): void {
    $dates = collect($this->expander->expand($this->start, new RecurrenceData(
        Frequency::Daily,
        until: CarbonImmutable::parse('2026-07-08 09:00'),
    )))->map(fn (CarbonImmutable $d) => $d->format('Y-m-d'));

    expect($dates->all())->toBe(['2026-07-06', '2026-07-07', '2026-07-08']);
});

it('filters by weekday', function (): void {
    // Monday (1) and Wednesday (3), for two weeks.
    $dates = collect($this->expander->expand($this->start, new RecurrenceData(
        Frequency::Weekly,
        count: 4,
        byWeekday: [1, 3],
    )))->map(fn (CarbonImmutable $d) => $d->format('Y-m-d'));

    expect($dates->all())->toBe(['2026-07-06', '2026-07-08', '2026-07-13', '2026-07-15']);
});

it('respects the configured occurrence cap', function (): void {
    config()->set('appointments.recurrence.max_occurrences', 5);

    $dates = $this->expander->expand($this->start, new RecurrenceData(Frequency::Daily, count: 100));

    expect($dates)->toHaveCount(5);
});
