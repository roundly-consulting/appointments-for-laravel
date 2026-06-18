<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Facades;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Appointments\AppointmentBuilder;
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * @method static AppointmentBuilder for(string $name)
 * @method static Appointment create(AppointmentData $data)
 * @method static Appointment reschedule(Appointment $appointment, CarbonImmutable $startsAt, ?int $durationMinutes = null, bool $preventConflicts = false)
 * @method static Appointment transition(Appointment $appointment, Status $to)
 *
 * @see AppointmentManager
 */
final class Appointments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AppointmentManager::class;
    }
}
