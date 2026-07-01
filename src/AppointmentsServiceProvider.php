<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Appointments\Commands\ExpireAppointmentApprovalsCommand;
use RoundlyConsulting\Appointments\Listeners\SyncAppointmentStatusFromApproval;
use RoundlyConsulting\Appointments\Reviews\NullVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;

final class AppointmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/appointments.php', 'appointments');

        $this->app->singleton(AppointmentManager::class);

        /** @var class-string<VerifiedAttendanceResolver> $resolver */
        $resolver = config('appointments.reviews.verified_attendance_resolver', NullVerifiedAttendanceResolver::class);

        $this->app->bind(VerifiedAttendanceResolver::class, $resolver);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Event::listen(ApprovalRequestResolved::class, SyncAppointmentStatusFromApproval::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireAppointmentApprovalsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/appointments.php' => config_path('appointments.php'),
            ], 'appointments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'appointments-migrations');
        }
    }
}
