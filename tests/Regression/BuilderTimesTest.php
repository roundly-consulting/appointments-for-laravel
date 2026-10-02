<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\Exceptions\InvalidScheduleException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;

it('measures until() against the start whichever is called first', function (): void {
    $appointment = Appointments::schedule('Late start')
        ->until('2026-07-01 19:00')
        ->startingAt('2026-07-01 17:00')
        ->create();

    expect($appointment->durationInMinutes())->toBe(120)
        ->and($appointment->ends_at->format('Y-m-d H:i'))->toBe('2026-07-01 19:00');
});

it('lets the later of lasting() and until() win', function (): void {
    $until = Appointments::schedule('Until wins')->startingAt('2026-07-01 17:00')->lasting(15)->until('2026-07-01 18:00')->create();
    $lasting = Appointments::schedule('Lasting wins')->startingAt('2026-07-01 17:00')->until('2026-07-01 18:00')->lasting(15)->create();

    expect($until->durationInMinutes())->toBe(60)
        ->and($lasting->durationInMinutes())->toBe(15);
});

it('refuses an end that does not come after the start', function (string $end): void {
    Appointments::schedule('Backwards')->startingAt('2026-07-01 17:00')->until($end)->create();
})->with(['2026-07-01 16:00', '2026-07-01 17:00'])->throws(InvalidScheduleException::class, 'must end after it starts');

it('refuses a duration that is not positive', function (int $minutes): void {
    Appointments::schedule('Negative')->startingAt('2026-07-01 17:00')->lasting($minutes);
})->with([0, -30])->throws(InvalidScheduleException::class, 'at least one minute');

it('refuses a non-positive duration on the DTO', function (): void {
    new AppointmentData(name: 'Negative', startsAt: CarbonImmutable::parse('2026-07-01 17:00'), durationMinutes: -60);
})->throws(InvalidScheduleException::class);

it('refuses a non-positive duration on reschedule and keeps the booking', function (): void {
    $appointment = Appointments::schedule('Kept')->startingAt('2026-07-01 17:00')->lasting(30)->create();

    expect(fn () => Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-02 17:00'), durationMinutes: 0))
        ->toThrow(InvalidScheduleException::class);

    expect($appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-07-01 17:00')
        ->and(Appointment::query()->count())->toBe(1);
});
