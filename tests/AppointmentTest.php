<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\AppointmentUpdated;
use RoundlyConsulting\Appointments\Models\Appointment;

it('casts status to enum', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->status)
        ->toBeInstanceOf(Status::class);
});

it('casts status appointment_at to Carbon instance', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->appointment_at)
        ->toBeInstanceOf(Carbon::class);
});

it('casts meta data as collection', function () {
    $appointment = Appointment::factory()->create([
        'meta' => collect([
            'something' => 'okay',
            'nothings' => 'fine',
        ]),
    ]);

    expect($appointment->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()
        ->toBe([
            'something' => 'okay',
            'nothings' => 'fine',
        ]);
});

it('dispatches AppointmentCreated event', function () {
    Event::fake();

    $appointment = Appointment::factory()->create();

    Event::assertDispatched(AppointmentCreated::class, fn (AppointmentCreated $e) => $e->appointment->is($appointment));
});

it('dispatches AppointmentUpdated event', function () {
    Event::fake();

    $appointment = Appointment::factory()->create();
    $appointment->update(['meta' => null]);

    Event::assertDispatched(AppointmentUpdated::class, fn (AppointmentUpdated $e) => $e->appointment->is($appointment));
});

it('has relationship to participants', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->participants())
        ->toBeInstanceOf(HasMany::class);
});

it('defaults status to New when omitted', function () {
    $appointment = Appointment::create([
        'name' => 'Standup',
        'appointment_at' => now(),
    ]);

    expect($appointment->fresh()->status)->toBe(Status::New);
});

it('soft deletes an appointment', function () {
    $appointment = Appointment::factory()->create();

    $appointment->delete();

    expect($appointment->trashed())->toBeTrue()
        ->and(Appointment::withTrashed()->find($appointment->getKey()))->not->toBeNull()
        ->and(Appointment::find($appointment->getKey()))->toBeNull();
});
