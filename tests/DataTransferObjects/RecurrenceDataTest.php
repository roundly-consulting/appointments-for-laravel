<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;

it('defaults interval, until, count and byWeekday', function (): void {
    $rule = new RecurrenceData(Frequency::Weekly);

    expect($rule)
        ->frequency->toBe(Frequency::Weekly)
        ->interval->toBe(1)
        ->until->toBeNull()
        ->count->toBeNull()
        ->byWeekday->toBe([]);
});

it('captures explicit recurrence bounds', function (): void {
    $until = CarbonImmutable::parse('2026-08-01');
    $rule = new RecurrenceData(Frequency::Daily, interval: 2, until: $until, count: 5, byWeekday: [1, 3]);

    expect($rule)
        ->interval->toBe(2)
        ->until->toBe($until)
        ->count->toBe(5)
        ->byWeekday->toBe([1, 3]);
});
