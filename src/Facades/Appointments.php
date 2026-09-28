<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Facades;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Appointments\AppointmentBuilder;
use RoundlyConsulting\Appointments\AppointmentHandle;
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Testing\AppointmentsFake;

/**
 * @method static AppointmentBuilder schedule(string $name)
 * @method static Appointment create(AppointmentData $data)
 * @method static Collection<int, Appointment> createRecurring(AppointmentData $data, ?RecurrenceData $rule = null)
 * @method static AppointmentHandle for(Appointment $appointment)
 * @method static Collection<int, Appointment> conflicts(Model $participant, CarbonInterface $start, CarbonInterface $end, ?Appointment $ignore = null)
 * @method static bool isAvailable(Model $participant, CarbonInterface $start, CarbonInterface $end, ?Appointment $ignore = null)
 * @method static string ics(iterable<Appointment> $appointments)
 * @method static list<CarbonImmutable> occurrences(CarbonInterface $start, RecurrenceData $rule)
 * @method static void assertScheduled(Closure|null $callback = null)
 * @method static void assertNothingScheduled()
 * @method static void assertRescheduled(Closure|null $callback = null)
 * @method static void assertNothingRescheduled()
 * @method static void assertTransitioned(Closure|null $callback = null)
 * @method static void assertNothingTransitioned()
 * @method static void assertParticipantAdded(Closure|null $callback = null)
 * @method static void assertNoParticipantAdded()
 * @method static void assertParticipantRemoved(Closure|null $callback = null)
 * @method static void assertNoParticipantRemoved()
 *
 * @see AppointmentManager
 * @see AppointmentsFake
 */
final class Appointments extends Facade
{
    /**
     * Swap the manager for a recording fake — behind the facade and in the container, so an
     * injected AppointmentManager is faked too. Operations still run against the database while
     * every mutation (facade, injected manager, builder, handle or Appointment model method) is
     * recorded for the `assert*()` methods.
     */
    public static function fake(): AppointmentsFake
    {
        $fake = app(AppointmentsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AppointmentManager::class;
    }
}
