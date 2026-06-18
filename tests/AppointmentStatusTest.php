<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentStatusChanged;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Models\Appointment;

it('confirms a pending appointment', function (): void {
    Event::fake();
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    $appointment->confirm();

    expect($appointment->status)->toBe(Status::Confirmed);
    Event::assertDispatched(AppointmentStatusChanged::class);
});

it('cancels a pending appointment', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    expect($appointment->cancel()->status)->toBe(Status::Cancelled);
});

it('declines a pending appointment', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    expect($appointment->decline()->status)->toBe(Status::Declined);
});

it('completes a confirmed appointment', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Confirmed)->create();

    expect($appointment->complete()->status)->toBe(Status::Completed);
});

it('marks a confirmed appointment as a no-show', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Confirmed)->create();

    expect($appointment->markNoShow()->status)->toBe(Status::NoShow);
});

it('blocks completing a pending appointment', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    $appointment->complete();
})->throws(InvalidStatusTransitionException::class);
