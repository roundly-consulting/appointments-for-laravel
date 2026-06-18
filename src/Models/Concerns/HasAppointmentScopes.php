<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Appointments\Enums\Status;

/**
 * @phpstan-require-extends Model
 */
trait HasAppointmentScopes
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', Carbon::now());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePast(Builder $query): void
    {
        $query->where('starts_at', '<', Carbon::now());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('starts_at', [$from, $to]);
    }

    /**
     * Appointments whose time range overlaps the given window.
     *
     * Touching ranges (one ends exactly when the other starts) do not overlap.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query
            ->where('starts_at', '<', $end)
            ->where(function (Builder $inner) use ($start): void {
                $inner
                    ->where('ends_at', '>', $start)
                    ->orWhereNull('ends_at');
            });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithStatus(Builder $query, Status ...$statuses): void
    {
        $query->whereIn('status', array_map(static fn (Status $status): string => $status->value, $statuses));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForParticipant(Builder $query, Model $participant): void
    {
        $participants = $this->participants()->getRelated()->newQuery()
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey())
            ->select('appointment_id');

        $query->whereIn($this->getKeyName(), $participants);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('starts_at');
    }
}
