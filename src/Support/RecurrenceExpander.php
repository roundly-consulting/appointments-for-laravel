<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;

/**
 * @internal reach it through `Appointments::occurrences()`
 */
final class RecurrenceExpander
{
    /**
     * Expand a base start time into a list of occurrence start times, in the start's own
     * timezone (so a daily 09:00 stays 09:00 local across a daylight-saving change).
     *
     * Without a weekday filter the first occurrence is the base start and each next one is
     * `interval` days / weeks / months on. With one, every listed weekday of each active period
     * occurs — the period being the day, the week (Monday-based, counted from the start's week)
     * or the month the frequency names, and every `interval`-th period being active — from the
     * base start on.
     *
     * Expansion is bounded by the rule's count/until and the
     * `appointments.recurrence.max_occurrences` cap.
     *
     * @return list<CarbonImmutable>
     */
    public function expand(CarbonImmutable $start, RecurrenceData $rule): array
    {
        return $rule->byWeekday === []
            ? $this->byInterval($start, $rule)
            : $this->byWeekday($start, $rule);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function byInterval(CarbonImmutable $start, RecurrenceData $rule): array
    {
        $cap = $this->cap($rule);
        $occurrences = [];

        for ($step = 0; count($occurrences) < $cap; $step++) {
            $offset = $step * $rule->interval;

            // Always computed from the base start, so month-end clamping does not compound.
            $cursor = match ($rule->frequency) {
                Frequency::Daily => $start->addDays($offset),
                Frequency::Weekly => $start->addWeeks($offset),
                Frequency::Monthly => $start->addMonthsNoOverflow($offset),
            };

            if ($this->isPastUntil($cursor, $rule)) {
                break;
            }

            $occurrences[] = $cursor;
        }

        return $occurrences;
    }

    /**
     * Walk day by day from the start, keeping the listed weekdays of active periods only.
     *
     * @return list<CarbonImmutable>
     */
    private function byWeekday(CarbonImmutable $start, RecurrenceData $rule): array
    {
        $cap = $this->cap($rule);
        $occurrences = [];

        // A rule whose filter can never meet an active period (a daily interval of 7 on another
        // weekday) would walk forever; bound it by the longest gap a valid rule can have.
        $maxSteps = ($cap + 1) * $rule->interval * ($rule->frequency === Frequency::Monthly ? 31 : 7);

        for ($step = 0; count($occurrences) < $cap && $step <= $maxSteps; $step++) {
            $cursor = $start->addDays($step);

            if ($this->isPastUntil($cursor, $rule)) {
                break;
            }

            if ($this->period($start, $cursor, $step, $rule->frequency) % $rule->interval === 0
                && in_array($cursor->dayOfWeekIso, $rule->byWeekday, strict: true)) {
                $occurrences[] = $cursor;
            }
        }

        return $occurrences;
    }

    /**
     * Which day / week / month (zero-based, counted from the start's) the cursor falls in.
     */
    private function period(CarbonImmutable $start, CarbonImmutable $cursor, int $step, Frequency $frequency): int
    {
        return match ($frequency) {
            Frequency::Daily => $step,
            Frequency::Weekly => intdiv($step + $start->dayOfWeekIso - 1, 7),
            Frequency::Monthly => ($cursor->year - $start->year) * 12 + $cursor->month - $start->month,
        };
    }

    /**
     * An `until` at midnight is a date (RFC 5545's date-only UNTIL): the whole day counts, compared
     * as a calendar date. Any other `until` is the last instant an occurrence may start.
     */
    private function isPastUntil(CarbonImmutable $cursor, RecurrenceData $rule): bool
    {
        $until = $rule->until;

        if ($until === null) {
            return false;
        }

        if ($until->format('H:i:s.u') === '00:00:00.000000') {
            return $cursor->toDateString() > $until->toDateString();
        }

        return $cursor->greaterThan($until);
    }

    private function cap(RecurrenceData $rule): int
    {
        /** @var int $max */
        $max = config('appointments.recurrence.max_occurrences', 365);

        if ($rule->count !== null) {
            return min($rule->count, $max);
        }

        return $max;
    }
}
