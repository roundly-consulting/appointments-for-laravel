<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Appointments\AppointmentsServiceProvider;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Reviews\DatabaseVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\NullVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Support\RecurrenceExpander;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/*
 | A typo in a host's config must fail loudly, never quietly become a default. The three
 | switches used to be read with a `(bool)` cast, so `'off'` and `'false'` switched them ON
 | and a typo such as `'disabled'` did too. They now go through the toolkit's strict
 | `Config::boolean()`.
 */

function appointmentsSwitch(string $key, bool $default = false): string
{
    return (new ReflectionMethod(AppointmentsServiceProvider::class, 'switch'))->invoke(null, $key, $default);
}

it('reports env-style switch strings as what they say', function (string $key): void {
    config()->set($key, 'off');
    expect(appointmentsSwitch($key))->toBe('OFF');

    config()->set($key, 'yes');
    expect(appointmentsSwitch($key))->toBe('ON');
})->with([
    'appointments.prevent_conflicts',
    'appointments.reviews.require_verified_attendance',
    'appointments.approvals.enforce_transitions',
]);

it('refuses a mistyped switch in the about section (strict config)', function (string $key): void {
    config()->set($key, 'disabled');

    expect(fn () => appointmentsSwitch($key))
        ->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a boolean");
})->with([
    'appointments.prevent_conflicts',
    'appointments.reviews.require_verified_attendance',
    'appointments.approvals.enforce_transitions',
]);

it('reads a string "false" attendance requirement as off', function (): void {
    config()->set('appointments.reviews.require_verified_attendance', 'false');

    $appointment = Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create();

    expect($appointment->review(User::create())->rating(3)->create()->verified)->toBeFalse();
});

it('refuses a mistyped attendance requirement (strict config)', function (): void {
    config()->set('appointments.reviews.require_verified_attendance', 'required');

    $appointment = Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create();

    expect(fn () => $appointment->review(User::create()))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [appointments.reviews.require_verified_attendance] must be a boolean');
});

it('refuses a mistyped transition guard when syncing an approval (strict config)', function (): void {
    config()->set('appointments.approvals.enforce_transitions', 'enforcing');

    $organiser = User::create();
    $appointment = Appointments::schedule('Booking request')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    expect(fn () => Approvals::for($appointment)->as($organiser)->approve())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [appointments.approvals.enforce_transitions] must be a boolean');
});

it('refuses to migrate on an unrecognized key type (strict config)', function (): void {
    config()->set('appointments.key_type', 'nonsense');

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/0002_create_appointment_participants_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [appointments.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});

/*
 | The non-switch settings were read unvalidated: `(int)` turned a default duration of
 | `'an hour'` into 0 (an appointment ending as it starts), the recurrence cap was handed to
 | `min()` as whatever it was, a blank table name reached SQL, and a timezone that was not a
 | string quietly became the app's.
 */

it('refuses a junk or non-positive default duration (strict config)', function (mixed $minutes): void {
    config()->set('appointments.default_duration_minutes', $minutes);

    expect(fn () => Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create())
        ->toThrow(InvalidConfigurationException::class, 'appointments.default_duration_minutes')
        ->and(fn () => (new Appointment)->durationInMinutes())
        ->toThrow(InvalidConfigurationException::class, 'appointments.default_duration_minutes');
})->with(['word' => 'an hour', 'decimal' => '7.5', 'zero' => 0, 'negative' => '-15']);

it('reads a canonical default duration string and an unset one as 60 (strict config)', function (?string $unset): void {
    config()->set('appointments.default_duration_minutes', '45');
    $set = Appointments::schedule('Short')->startingAt('2026-07-01 09:00')->create();

    config()->set('appointments.default_duration_minutes', $unset);
    $absent = Appointments::schedule('Default')->startingAt('2026-07-01 09:00')->create();

    expect($set->duration_minutes)->toBe(45)
        ->and($absent->duration_minutes)->toBe(60)
        ->and((new Appointment)->durationInMinutes())->toBe(60);
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('refuses a junk or non-positive occurrence cap (strict config)', function (mixed $max): void {
    config()->set('appointments.recurrence.max_occurrences', $max);

    expect(fn () => app(RecurrenceExpander::class)->expand(CarbonImmutable::parse('2026-07-06 09:00'), new RecurrenceData(Frequency::Daily, count: 10)))
        ->toThrow(InvalidConfigurationException::class, 'appointments.recurrence.max_occurrences');
})->with(['word' => 'unlimited', 'zero' => 0, 'over the ceiling' => 100_001]);

it('caps at 365 occurrences when no cap is set (strict config)', function (?string $unset): void {
    config()->set('appointments.recurrence.max_occurrences', $unset);

    expect(app(RecurrenceExpander::class)->expand(CarbonImmutable::parse('2026-07-06 09:00'), new RecurrenceData(Frequency::Daily, count: 400)))
        ->toHaveCount(365);
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('refuses a non-string table name (strict config)', function (string $key, Closure $table, mixed $value): void {
    config()->set($key, $value);

    expect($table)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'appointments' => ['appointments.table_names.appointments', fn () => (new Appointment)->getTable()],
    'participants' => ['appointments.table_names.participants', fn () => (new Participant)->getTable()],
])->with(['array' => [['appointments']], 'integer' => 1]);

it('refuses to migrate onto a non-string table name (strict config)', function (): void {
    config()->set('appointments.table_names.participants', ['appointment_participants']);

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/0002_create_appointment_participants_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'appointments.table_names.participants');
});

it('migrates onto the packaged table name when none is set (strict config)', function (?string $unset): void {
    config()->set('appointments.table_names.participants', $unset);

    Schema::dropIfExists('appointment_participants');

    $migration = require __DIR__.'/../../database/migrations/0002_create_appointment_participants_table.php';
    $migration->up();

    expect(Schema::hasTable('appointment_participants'))->toBeTrue();
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('uses the packaged table names when none are set (strict config)', function (?string $unset): void {
    config()->set('appointments.table_names.appointments', $unset);
    config()->set('appointments.table_names.participants', $unset);

    expect((new Appointment)->getTable())->toBe('appointments')
        ->and((new Participant)->getTable())->toBe('appointment_participants');
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('refuses a timezone that is not a timezone (strict config)', function (mixed $timezone): void {
    config()->set('appointments.timezone', $timezone);

    expect(fn () => Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create())
        ->toThrow(InvalidConfigurationException::class, 'appointments.timezone');
})->with(['typo' => 'Europe/Bratislva', 'array' => [['UTC']], 'integer' => 2]);

it('uses the app timezone when none is set (strict config)', function (?string $unset): void {
    config()->set('app.timezone', 'America/New_York');
    config()->set('appointments.timezone', $unset);

    expect(Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->create()->timezone)->toBe('America/New_York');
})->with(['absent' => null, 'blank' => '', 'whitespace' => ' ']);

it('binds the shipped database attendance resolver when none is set (strict config)', function (array $reviews): void {
    // Not set — left out, null or a host's blank `KEY=` — is the shipped resolver, never the
    // null one that silently stops verifying every review.
    config()->set('appointments.reviews', $reviews);

    Artisan::call('about', ['--only' => 'appointments']);

    expect(app(VerifiedAttendanceResolver::class))->toBeInstanceOf(DatabaseVerifiedAttendanceResolver::class)
        ->and(Artisan::output())->toMatch('/Attendance resolver \.+ DatabaseVerifiedAttendanceResolver/');
})->with([
    'absent' => [['require_verified_attendance' => false]],
    'null' => [['verified_attendance_resolver' => null]],
    'blank' => [['verified_attendance_resolver' => '']],
    'whitespace' => [['verified_attendance_resolver' => ' ']],
]);

it('binds the attendance resolver a host names explicitly (strict config)', function (string $resolver): void {
    config()->set('appointments.reviews.verified_attendance_resolver', $resolver);

    Artisan::call('about', ['--only' => 'appointments']);

    expect(app(VerifiedAttendanceResolver::class))->toBeInstanceOf($resolver)
        ->and(Artisan::output())->toMatch('/Attendance resolver \.+ '.class_basename($resolver).'/');
})->with([
    'null resolver, opted into' => NullVerifiedAttendanceResolver::class,
    'database resolver' => DatabaseVerifiedAttendanceResolver::class,
]);

it('refuses an attendance resolver that is not one (strict config)', function (mixed $resolver): void {
    config()->set('appointments.reviews.verified_attendance_resolver', $resolver);

    expect(fn () => app(VerifiedAttendanceResolver::class))
        ->toThrow(InvalidConfigurationException::class, 'appointments.reviews.verified_attendance_resolver');
})->with([
    'missing class' => 'App\\Missing',
    'wrong class' => Appointment::class,
    'array' => [[NullVerifiedAttendanceResolver::class]],
]);

it('keeps the about section rendering on a malformed host config (strict config)', function (): void {
    config()->set('appointments.default_duration_minutes', 'an hour');
    config()->set('appointments.recurrence.max_occurrences', 'unlimited');
    config()->set('appointments.table_names.appointments', ['appointments']);
    config()->set('appointments.timezone', 'Europe/Bratislva');
    config()->set('appointments.reviews.verified_attendance_resolver', 'App\\Missing');

    Artisan::call('about', ['--only' => 'appointments']);

    expect(Artisan::output())
        ->toMatch('/Appointments table \.+ INVALID/')
        ->toMatch('/Default timezone \.+ INVALID/')
        ->toMatch('/Default duration \.+ INVALID/')
        ->toMatch('/Max occurrences \.+ INVALID/')
        ->toMatch('/Attendance resolver \.+ INVALID/')
        ->not->toContain('Bratislva');
});
