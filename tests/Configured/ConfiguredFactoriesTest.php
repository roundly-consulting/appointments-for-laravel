<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\CustomAppointment;

/**
 * The shipped factory builds the model the host configured. Without a `modelName()` override
 * it built its packaged `$model`, so a host seeding through `Appointment::factory()` got
 * packaged rows — without its own casts, events or observers. Exact classes, not `instanceof`:
 * the custom model extends the packaged one, so `toBeInstanceOf` passes either way.
 */
it('builds the configured appointment model', function (): void {
    expect(Appointment::factory()->create()::class)->toBe(CustomAppointment::class)
        ->and(CustomAppointment::factory()->create()::class)->toBe(CustomAppointment::class);
});

it('fires the host model events for a factory-built appointment', function (): void {
    CustomAppointment::resetCreationCount();

    Appointment::factory()->count(2)->create();

    expect(CustomAppointment::creationCount())->toBe(2);
});
