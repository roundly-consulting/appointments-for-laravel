<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Actions\TransitionAppointmentAction;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentStatusChanged;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Models\Appointment;

beforeEach(function (): void {
    $this->action = app(TransitionAppointmentAction::class);
});

it('transitions an appointment and fires the status changed event', function (): void {
    Event::fake();
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    $this->action->execute($appointment, Status::Confirmed);

    expect($appointment->fresh()->status)->toBe(Status::Confirmed);

    Event::assertDispatched(
        AppointmentStatusChanged::class,
        fn (AppointmentStatusChanged $e) => $e->from === Status::Pending
            && $e->to === Status::Confirmed
            && $e->appointment->is($appointment),
    );
});

it('is a no-op when transitioning to the same status', function (): void {
    Event::fake();
    $appointment = Appointment::factory()->withStatus(Status::Confirmed)->create();

    $result = $this->action->execute($appointment, Status::Confirmed);

    expect($result->status)->toBe(Status::Confirmed);
    Event::assertNotDispatched(AppointmentStatusChanged::class);
});

it('throws on an illegal transition', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Completed)->create();

    $this->action->execute($appointment, Status::Confirmed);
})->throws(InvalidStatusTransitionException::class);

it('exposes from and to on the exception', function (): void {
    $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

    try {
        $this->action->execute($appointment, Status::Completed);
    } catch (InvalidStatusTransitionException $e) {
        expect($e->from)->toBe(Status::Pending)
            ->and($e->to)->toBe(Status::Completed);

        return;
    }

    $this->fail('Expected InvalidStatusTransitionException.');
});
