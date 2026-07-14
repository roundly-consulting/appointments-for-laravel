<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    protected function getEnvironmentSetUp($app): void
    {
        $this->defineEnvironment($app);

        $this->loadProviderSchema();

        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $file) {
            $migration = include $file;

            if ($migration instanceof Migration) {
                $migration->up();
            }
        }

        Schema::create('users', fn (Blueprint $table) => $table->id());
    }

    /**
     * Run the provider migrations the appointment integrations depend on, each in
     * dependency order, from their own package directories.
     */
    private function loadProviderSchema(): void
    {
        $migrations = [
            ApprovalsServiceProvider::class => [
                'create_approvals_table',
                'create_approval_requests_table',
                'add_v11_columns_to_approvals_table',
                'add_staging_to_approval_requests_table',
                'create_approval_request_stages_table',
                'create_approval_delegations_table',
            ],
            ContactsServiceProvider::class => [
                'create_contacts_table',
            ],
            // reviews-for-laravel attaches media to reviews, so its summary query
            // reads the media table even when no review has an attachment.
            MediaLibraryServiceProvider::class => [
                '0001_01_01_000000_create_media_table',
            ],
            ReviewsServiceProvider::class => [
                'create_reviews_table',
                'create_review_votes_table',
            ],
        ];

        foreach ($migrations as $provider => $names) {
            $base = dirname((string) (new ReflectionClass($provider))->getFileName(), 2);

            foreach ($names as $name) {
                $migration = require "{$base}/database/migrations/{$name}.php";

                if ($migration instanceof Migration) {
                    $migration->up();
                }
            }
        }
    }
}
