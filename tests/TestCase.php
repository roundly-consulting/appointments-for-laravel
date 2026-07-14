<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Appointments\AppointmentsServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Geolocation\GeolocationServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

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
            ApprovalsServiceProvider::class,
            ContactsServiceProvider::class,
            GeolocationServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
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

    /**
     * No package auto-loads its migrations (they are publish-only), so the suite runs
     * them itself — exactly like a host app does after publishing. Every provider's
     * schema is loaded by *directory*: each directory's filenames already sort into
     * dependency order, and naming the files here would break the moment a provider
     * renames one.
     */
    protected function defineDatabaseMigrations(): void
    {
        // approvals backs the appointment approval flow; contacts backs attendee lookups;
        // reviews backs post-appointment reviews, and its summary query reads media-library's
        // `media` table even when no review carries an attachment.
        foreach ([
            ApprovalsServiceProvider::class,
            ContactsServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
        ] as $provider) {
            $this->loadMigrationsFrom($this->migrationsPathFor($provider));
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', fn (Blueprint $table) => $table->id());
    }

    /**
     * A provider package's migrations directory, resolved from wherever composer put it
     * (a symlinked path repository locally, a real install from VCS on CI).
     *
     * @param  class-string<ServiceProvider>  $provider
     */
    private function migrationsPathFor(string $provider): string
    {
        $base = dirname((string) (new ReflectionClass($provider))->getFileName(), 2);

        return $base.'/database/migrations';
    }
}
