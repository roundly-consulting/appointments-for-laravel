<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\Commands\ExpireAppointmentApprovalsCommand;
use RoundlyConsulting\Appointments\Listeners\SyncAppointmentStatusFromApproval;
use RoundlyConsulting\Appointments\Reviews\NullVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\ParticipantModel;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

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
                'Appointments table' => self::tableName('appointments'),
                'Participants table' => self::tableName('participants'),
                'Default timezone' => config('appointments.timezone') === null ? 'APP DEFAULT' : 'SET',
                'Default duration' => self::defaultDuration().' min',
                'Prevent conflicts' => self::switch('appointments.prevent_conflicts', false),
                'Max occurrences' => self::maxOccurrences(),
                'Attendance resolver' => class_basename(self::resolverClass()),
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
     * Literal keys rather than `config("appointments.table_names.{$key}")`: an interpolated
     * key cannot be checked against the shipped config file, and an unverifiable read is how
     * a package ends up reading a key it never ships (shops #18) or shipping one nothing
     * reads (media #27's size cap that never applied). The match is exhaustive over the two
     * tables the package owns, so a new table has to be named here rather than silently
     * resolving to its own key. Same shape and same remedy as cosmos-foundation's
     * rate_limiters match.
     */
    private static function tableName(string $key): string
    {
        $table = match ($key) {
            'appointments' => config('appointments.table_names.appointments'),
            'participants' => config('appointments.table_names.participants'),
            default => null,
        };

        return is_string($table) && $table !== '' ? $table : $key;
    }

    private static function defaultDuration(): string
    {
        $minutes = config('appointments.default_duration_minutes', 60);

        return (string) (is_numeric($minutes) ? (int) $minutes : 60);
    }

    private static function maxOccurrences(): string
    {
        $max = config('appointments.recurrence.max_occurrences', 365);

        return (string) (is_numeric($max) ? (int) $max : 365);
    }

    private static function resolverClass(): string
    {
        $resolver = config('appointments.reviews.verified_attendance_resolver', NullVerifiedAttendanceResolver::class);

        return is_string($resolver) && $resolver !== '' ? $resolver : NullVerifiedAttendanceResolver::class;
    }

    private static function switch(string $key, bool $default): string
    {
        return (bool) config($key, $default) ? 'ON' : 'OFF';
    }
}
