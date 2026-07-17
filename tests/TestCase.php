<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Appointments\AppointmentsServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Geolocation\GeolocationServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'RoundlyConsulting\\Appointments\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    /**
     * Every provider appointments hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
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

    /**
     * No package auto-loads its migrations (they are publish-only), so the suite runs them
     * itself — exactly like a host app does after publishing. Every source is named by
     * **provider class**, never by a hand-resolved path: the base case reflects each provider
     * to its own `database/migrations`, so this keeps working when a provider renames a file
     * or composer moves the package between a symlinked path repo and a real VCS install.
     *
     * approvals backs the appointment approval flow; contacts backs attendee lookups; reviews
     * backs post-appointment reviews, and its summary query reads media-library's `media`
     * table even when no review carries an attachment.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            ApprovalsServiceProvider::class,
            ContactsServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
            AppointmentsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }
}
