<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('reads and writes the table named by appointments.table_names.appointments', function (): void {
    // What the migration creates under that config.
    Schema::rename('appointments', 'bookings');
    config()->set('appointments.table_names.appointments', 'bookings');

    $host = User::create();

    $appointment = Appointments::schedule('Renamed')
        ->startingAt('2026-07-01 09:00')
        ->withParticipant($host)
        ->preventConflicts()
        ->create();

    expect((new Appointment)->getTable())->toBe('bookings')
        ->and(DB::table('bookings')->count())->toBe(1)
        ->and($host->appointments()->count())->toBe(1)
        ->and($appointment->participants()->first()?->appointment?->is($appointment))->toBeTrue();
});
