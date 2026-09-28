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
     * Expand a base start time into a list of occurrence start times.
     *
     * The first occurrence is always the base start. Expansion is bounded by
     * the rule's count/until and a hard config cap to avoid runaway growth.
     *
     * @return list<CarbonImmutable>
     */
    public function expand(CarbonImmutable $start, RecurrenceData $rule): array
    {
        $cap = $this->cap($rule);
        $occurrences = [];
        $step = 0;

        // Guard against an unbounded weekday filter that never matches.
        $maxSteps = $cap * 7 + 7;

        while (count($occurrences) < $cap && $step <= $maxSteps) {
            $cursor = $this->occurrenceAt($start, $rule, $step);
            $step++;

            if ($rule->until !== null && $cursor->greaterThan($rule->until)) {
                break;
            }

            if ($this->matchesWeekday($cursor, $rule)) {
                $occurrences[] = $cursor;
            }
        }

        return $occurrences;
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

    private function matchesWeekday(CarbonImmutable $cursor, RecurrenceData $rule): bool
    {
        if ($rule->byWeekday === []) {
            return true;
        }

        return in_array($cursor->dayOfWeekIso, $rule->byWeekday, strict: true);
    }

    /**
     * The occurrence for a given zero-based step, always computed from the base
     * start so month-end clamping does not compound across iterations.
     */
    private function occurrenceAt(CarbonImmutable $start, RecurrenceData $rule, int $step): CarbonImmutable
    {
        // With a weekday filter we walk day-by-day and let the filter select
        // matching days; otherwise we jump by the rule's own unit.
        if ($rule->byWeekday !== []) {
            return $start->addDays($step);
        }

        $offset = $step * $rule->interval;

        return match ($rule->frequency) {
            Frequency::Daily => $start->addDays($offset),
            Frequency::Weekly => $start->addWeeks($offset),
            Frequency::Monthly => $start->addMonthsNoOverflow($offset),
        };
    }
}
