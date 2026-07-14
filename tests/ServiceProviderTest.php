<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\Reviews\NullVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Tests\Models\CustomAppointment;

it('registers all publish tags', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(null, $tag))->not->toBeEmpty();
})->with([
    'appointments-config',
    'appointments-migrations',
]);

it('binds the appointment manager as a singleton', function (): void {
    expect(app(AppointmentManager::class))->toBe(app(AppointmentManager::class));
});

it('binds the verified attendance resolver from config', function (): void {
    config()->set('appointments.reviews.verified_attendance_resolver', NullVerifiedAttendanceResolver::class);

    expect(app(VerifiedAttendanceResolver::class))->toBeInstanceOf(NullVerifiedAttendanceResolver::class);
});

it('registers the package commands', function (): void {
    expect(array_keys(app(Kernel::class)->all()))->toContain('appointments:expire-approvals');
});

it('contributes an appointments section to about', function (string $expected): void {
    $this->artisan('about --only=appointments')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Appointments',
    'Model',
    'Participant model',
    'Default duration',
    'Prevent conflicts',
    'Max occurrences',
    'Attendance resolver',
    'Approval transitions',
]);

it('reports the configured host model in about', function (): void {
    config()->set('appointments.model', CustomAppointment::class);

    $this->artisan('about --only=appointments')
        ->expectsOutputToContain('CustomAppointment')
        ->assertExitCode(0);
});

it('reports the default timezone as presence only', function (): void {
    config()->set('appointments.timezone', 'Europe/Bratislava');

    $this->artisan('about --only=appointments')
        ->doesntExpectOutputToContain('Europe/Bratislava')
        ->assertExitCode(0);
});

it('reports the app default timezone when none is configured', function (): void {
    config()->set('appointments.timezone', null);

    $this->artisan('about --only=appointments')
        ->expectsOutputToContain('APP DEFAULT')
        ->assertExitCode(0);
});
