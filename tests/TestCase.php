<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Appointments\AppointmentsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'RoundlyConsulting\\Appointments\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AppointmentsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $this->defineEnvironment($app);

        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $file) {
            $migration = include $file;

            if ($migration instanceof Migration) {
                $migration->up();
            }
        }

        Schema::create('users', fn (Blueprint $table) => $table->id());
    }
}
