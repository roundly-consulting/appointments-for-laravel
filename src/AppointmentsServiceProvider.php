<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Illuminate\Support\ServiceProvider;

final class AppointmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/appointments.php', 'appointments');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/appointments.php' => config_path('appointments.php'),
            ], 'appointments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'appointments-migrations');
        }
    }
}
