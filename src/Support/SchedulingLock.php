<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Makes "is this participant free?" and "book it" one step.
 *
 * Every booking write runs in one transaction on the appointments connection. With conflict
 * prevention on, the same transaction first takes a row lock on each participant (and, when an
 * existing appointment is changed, on the appointment itself) and only then looks for a clash, so
 * two bookings for the same person serialize: the second one's conflict query sees the first
 * one's committed rows instead of both passing the check and both inserting.
 *
 * Locks are always taken in the same order — the appointment first, then participants sorted by
 * type and key — so two bookings that share participants cannot deadlock each other. SQLite has
 * no row locks; it serializes writers instead.
 *
 * @internal
 */
final class SchedulingLock
{
    /**
     * Whether a write must refuse a clash: asked for on the call, or `appointments.prevent_conflicts`.
     */
    public function preventing(bool $requested): bool
    {
        return $requested || Config::boolean('appointments.prevent_conflicts');
    }

    /**
     * Run the callback in one transaction on the appointments connection. A participant stored on
     * another connection gets a transaction of its own around that one, so the lock on its row
     * is held until the appointment write has committed.
     *
     * @template TResult
     *
     * @param  iterable<Model>  $participants
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function transaction(iterable $participants, Closure $callback): mixed
    {
        $main = $this->connection();

        $run = static fn (): mixed => $main->transaction($callback);

        foreach ($this->otherConnections($main, $participants) as $other) {
            $inner = $run;
            $run = static fn (): mixed => $other->transaction($inner);
        }

        /** @var TResult */
        return $run();
    }

    /**
     * Lock the appointment's row for the rest of the transaction and return it as stored now —
     * a concurrent reschedule may have moved it since the caller loaded it.
     */
    public function appointment(Appointment $appointment): Appointment
    {
        /** @var Appointment $locked */
        $locked = $appointment->newQueryWithoutScopes()
            ->whereKey($appointment->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    /**
     * Lock each participant's own row for the rest of the transaction, in a stable order.
     *
     * @param  iterable<Model>  $participants
     */
    public function participants(iterable $participants): void
    {
        $ordered = [];

        foreach ($participants as $participant) {
            if ($participant->exists) {
                $ordered[$participant->getMorphClass().'|'.$participant->getKey()] = $participant;
            }
        }

        ksort($ordered, SORT_STRING);

        foreach ($ordered as $participant) {
            $participant->newQueryWithoutScopes()
                ->whereKey($participant->getKey())
                ->lockForUpdate()
                ->value($participant->getKeyName());
        }
    }

    private function connection(): Connection
    {
        $model = AppointmentModel::class();

        return (new $model)->getConnection();
    }

    /**
     * @param  iterable<Model>  $participants
     * @return list<Connection>
     */
    private function otherConnections(Connection $main, iterable $participants): array
    {
        $others = [];

        foreach ($participants as $participant) {
            $connection = $participant->getConnection();

            if ($connection->getName() !== $main->getName()) {
                $others[$connection->getName()] = $connection;
            }
        }

        ksort($others, SORT_STRING);

        return array_values($others);
    }
}
