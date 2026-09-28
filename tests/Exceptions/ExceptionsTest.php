<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\AppointmentsException;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Exceptions\ParticipantNotFoundException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('describes an invalid status transition', function (): void {
    $exception = InvalidStatusTransitionException::make(Status::Completed, Status::Pending);

    expect($exception->from)->toBe(Status::Completed)
        ->and($exception->to)->toBe(Status::Pending)
        ->and($exception->getMessage())->toContain('completed')
        ->and($exception->getMessage())->toContain('pending');
});

it('carries the conflicting appointments', function (): void {
    $appointment = Appointment::factory()->create();
    /** @var Collection<int, Appointment> $conflicts */
    $conflicts = collect([$appointment]);

    $exception = SchedulingConflictException::make($conflicts);

    expect($exception->conflicts)->toHaveCount(1)
        ->and($exception->getMessage())->toContain('1 existing');
});

it('names the duplicate participant and, when known, the appointment', function (): void {
    $user = User::create();
    $appointment = Appointment::factory()->create();

    expect(DuplicateParticipantException::make($user, $appointment)->getMessage())
        ->toContain((string) $user->getKey())
        ->toContain("appointment [{$appointment->getKey()}]")
        ->and(DuplicateParticipantException::make($user)->getMessage())->toContain('this appointment')
        ->and(DuplicateParticipantException::make($user, new Appointment)->getMessage())->toContain('this appointment');
});

it('names the missing participant and the appointment', function (): void {
    $user = User::create();
    $appointment = Appointment::factory()->create();

    expect(ParticipantNotFoundException::make($user, $appointment))
        ->toBeInstanceOf(AppointmentsException::class)
        ->getMessage()->toContain("does not take part in appointment [{$appointment->getKey()}]");
});
