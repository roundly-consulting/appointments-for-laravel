<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;

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
