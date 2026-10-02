<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;

it('records no transition for a status the appointment already has', function (): void {
    $fake = Appointments::fake();
    $appointment = Appointments::schedule('Booked')->startingAt('2026-07-01 09:00')->withStatus(Status::Confirmed)->create();

    $appointment->confirm();
    Appointments::for($appointment)->confirm();

    $fake->assertNothingTransitioned();
});

it('records the real transition once when it is repeated', function (): void {
    $fake = Appointments::fake();
    $appointment = Appointments::schedule('Booked')->startingAt('2026-07-01 09:00')->create();

    $appointment->confirm();
    $appointment->confirm();

    $count = 0;
    $fake->assertTransitioned(function (Appointment $transitioned, Status $to, Status $from) use (&$count): bool {
        $count++;

        return $from === Status::Pending && $to === Status::Confirmed;
    });

    expect($count)->toBe(1);
});
