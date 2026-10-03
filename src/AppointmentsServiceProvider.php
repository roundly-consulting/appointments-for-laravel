<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Closure;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Commands\ExpireAppointmentApprovalsCommand;
use RoundlyConsulting\Appointments\Listeners\SyncAppointmentStatusFromApproval;
use RoundlyConsulting\Appointments\Reviews\NullVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\AppointmentsConfig;
use RoundlyConsulting\Appointments\Support\ParticipantModel;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

final class AppointmentsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('appointments')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasCommands([
                ExpireAppointmentApprovalsCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(AppointmentModel::class()),
                'Participant model' => class_basename(ParticipantModel::class()),
                // Table names are host schema, not secrets, so they render plainly;
                // the timezone is reported as presence only (it is a deployment
                // detail the host may treat as environment-specific).
                'Appointments table' => self::orInvalid(static fn (): string => AppointmentsConfig::appointmentsTable()),
                'Participants table' => self::orInvalid(static fn (): string => AppointmentsConfig::participantsTable()),
                'Default timezone' => self::orInvalid(static fn (): string => AppointmentsConfig::timezone() === null ? 'APP DEFAULT' : 'SET'),
                'Default duration' => self::orInvalid(static fn (): string => AppointmentsConfig::defaultDurationMinutes().' min'),
                'Prevent conflicts' => self::switch('appointments.prevent_conflicts', false),
                'Max occurrences' => self::orInvalid(static fn (): string => (string) AppointmentsConfig::maxOccurrences()),
                'Attendance resolver' => self::orInvalid(static fn (): string => class_basename(self::resolverClass())),
                'Require verified attendance' => self::switch('appointments.reviews.require_verified_attendance', false),
                'Approval transitions' => self::switch('appointments.approvals.enforce_transitions', false),
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AppointmentManager::class);

        $this->bindFromConfig(
            VerifiedAttendanceResolver::class,
            'appointments.reviews.verified_attendance_resolver',
            NullVerifiedAttendanceResolver::class,
        );
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        Event::listen(ApprovalRequestResolved::class, SyncAppointmentStatusFromApproval::class);
    }

    /**
     * The attendance resolver `bindFromConfig()` resolves: the null resolver when not set
     * (absent, null or blank), otherwise a VerifiedAttendanceResolver class — anything else
     * throws, as the binding does.
     *
     * @return class-string<VerifiedAttendanceResolver>
     */
    private static function resolverClass(): string
    {
        if (AppointmentsConfig::isUnset('appointments.reviews.verified_attendance_resolver')) {
            return NullVerifiedAttendanceResolver::class;
        }

        $resolver = config('appointments.reviews.verified_attendance_resolver');

        if (! is_string($resolver) || ! class_exists($resolver) || ! is_a($resolver, VerifiedAttendanceResolver::class, true)) {
            throw InvalidConfigurationException::notAnImplementation('appointments.reviews.verified_attendance_resolver', VerifiedAttendanceResolver::class, $resolver);
        }

        return $resolver;
    }

    /**
     * The value a strict read produces, or INVALID when the host's config is malformed:
     * `php artisan about` keeps rendering on a broken host, while the real read path throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    private static function switch(string $key, bool $default): string
    {
        return Config::boolean($key, $default) ? 'ON' : 'OFF';
    }
}
