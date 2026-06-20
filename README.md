<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/appointments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel">
    <img src="art/hero.png" alt="Appointments for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Appointments for Laravel

A scheduling toolkit for Laravel: create appointments with durations, end times, and time
zones; attach participants of any Eloquent model via a polymorphic relationship; detect
double-bookings; drive a guarded status lifecycle; generate recurring series; and export
standards-compliant calendar (`.ics`) files. Built on a native service provider with only
Laravel as a runtime dependency.

## Requirements

- PHP `^8.3`
- Laravel `^12.0` or `^13.0`

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/appointments-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="appointments-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="appointments-config"
```

Optionally publish the language files (status and role labels) to customise or translate them:

```bash
php artisan vendor:publish --tag="appointments-translations"
```

The package's migrations and translations are auto-discovered, so publishing is only needed
when you want to customise them.

## Configuration

The published config file lives at `config/appointments.php`:

```php
return [
    'model' => Appointment::class,
    'participant' => Participant::class,
    'table_names' => [
        'appointments' => 'appointments',
        'participants' => 'appointment_participants',
    ],
    'timezone' => null,
    'default_duration_minutes' => 60,
    'prevent_conflicts' => false,
    'recurrence' => [
        'max_occurrences' => 365,
    ],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Appointments\Models\Appointment` | The Eloquent model used for appointments. Point it at your own subclass to customise behaviour. |
| `participant` | `class-string` | `RoundlyConsulting\Appointments\Models\Participant` | The Eloquent model used for appointment participants. |
| `table_names.appointments` | `string` | `appointments` | Table name for appointments. |
| `table_names.participants` | `string` | `appointment_participants` | Table name for participants. |
| `timezone` | `?string` | `null` | Default timezone stored on an appointment. `null` falls back to `config('app.timezone')`. |
| `default_duration_minutes` | `int` | `60` | Duration applied when none is supplied, used to derive `ends_at` from `starts_at`. |
| `prevent_conflicts` | `bool` | `false` | When `true`, creating or rescheduling throws on an overlapping booking for a participant. Can also be enabled per call. |
| `recurrence.max_occurrences` | `int` | `365` | Hard cap on the number of occurrences a recurring series may expand to. |

## Usage

### Creating an appointment (fluent builder)

The `Appointments` facade exposes a fluent builder — the recommended API:

```php
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Facades\Appointments;

$appointment = Appointments::for('Project kickoff')
    ->startingAt('2026-07-01 17:30', timezone: 'Europe/Bratislava')
    ->lasting(90)                       // minutes; or ->until('2026-07-01 19:00')
    ->describedAs('The best chicken wings ever!')
    ->withMeta(['location' => 'My house'])
    ->withParticipant($host, ParticipantRole::Organiser, ['is_host' => true])
    ->withParticipant($guest)
    ->preventConflicts()                // opt-in double-booking guard for this call
    ->create();
```

### Creating an appointment (typed DTO + action)

For programmatic callers, build an `AppointmentData` and run the action:

```php
use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;

$appointment = app(CreateAppointmentAction::class)->execute(new AppointmentData(
    name: 'Project kickoff',
    startsAt: CarbonImmutable::parse('2026-07-01 17:30'),
    durationMinutes: 90,
    participants: [
        new ParticipantData($host, ParticipantRole::Organiser),
        new ParticipantData($guest),
    ],
));
```

Instants are stored in UTC; the appointment's `timezone` drives local display:

```php
$appointment->startsAtLocal();   // CarbonImmutable in the appointment's timezone
$appointment->endsAtLocal();
$appointment->duration();        // CarbonInterval
$appointment->durationInMinutes();
```

### Duration and end time

Provide a duration (`lasting()` / `durationMinutes`) or an explicit end (`until()` /
`ends_at`). When only one is given the other is derived; when neither is given the
`default_duration_minutes` config value is used.

### Status lifecycle

Status is a guarded state machine. Illegal transitions throw
`InvalidStatusTransitionException`; legal ones persist and fire `AppointmentStatusChanged`.

```php
use RoundlyConsulting\Appointments\Enums\Status;

$appointment->confirm();    // pending → confirmed
$appointment->cancel();     // pending/confirmed → cancelled
$appointment->complete();   // confirmed → completed
$appointment->decline();    // pending → declined
$appointment->markNoShow(); // confirmed → no_show

$appointment->status->label(); // "Confirmed" (translatable via appointments::status.*)
$appointment->status->color(); // a colour token for UI badges
```

Allowed transitions:

| From | To |
|---|---|
| `pending` | `confirmed`, `declined`, `cancelled` |
| `confirmed` | `completed`, `cancelled`, `no_show` |
| `cancelled`, `completed`, `declined`, `no_show` | _(final)_ |

### Conflict detection

With `prevent_conflicts` enabled (globally or per call), creating or rescheduling an
appointment that overlaps an existing booking for any participant throws
`SchedulingConflictException`, which carries the conflicting appointments. Touching ranges
(one ends exactly when the next begins) do not conflict; cancelled and declined appointments
are ignored.

```php
use RoundlyConsulting\Appointments\Support\ConflictDetector;

$conflicts = app(ConflictDetector::class)->forParticipant($user, $start, $end);
```

### Rescheduling

```php
use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Facades\Appointments;

Appointments::reschedule($appointment, CarbonImmutable::parse('2026-07-02 10:00'), durationMinutes: 60);
// fires AppointmentRescheduled
```

### Recurring appointments

```php
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Facades\Appointments;

$series = Appointments::for('Weekly standup')
    ->startingAt('2026-07-06 09:00')
    ->lasting(30)
    ->recurring(new RecurrenceData(Frequency::Weekly, count: 8, byWeekday: [1])) // Mondays
    ->createRecurring();
```

Each occurrence is materialised as its own appointment, linked by a shared `recurrence_group`
UUID. Recurrence supports `daily`/`weekly`/`monthly` frequencies, an `interval`, a `count` or
`until` bound, and `byWeekday` filtering. Expansion is capped by `recurrence.max_occurrences`.

### Querying

Scopes on the appointment model:

```php
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

Appointment::query()->upcoming()->forParticipant($user)->ordered()->get();
Appointment::query()->between($from, $to)->get();
Appointment::query()->overlapping($start, $end)->get();
Appointment::query()->withStatus(Status::Confirmed, Status::Completed)->get();
Appointment::query()->past()->get();
```

Add the `HasAppointments` trait to any host model to expose its appointments:

```php
use RoundlyConsulting\Appointments\Concerns\HasAppointments;

class User extends Authenticatable
{
    use HasAppointments;
}

$user->appointments()->upcoming()->get();   // appointments the user takes part in
$user->appointmentParticipations;            // the participant rows
```

### Calendar (ICS) export

Generate RFC 5545 calendar text for one appointment or a collection — the package stays
HTTP-free, so you decide how to deliver it:

```php
use RoundlyConsulting\Appointments\Support\Ics\IcsGenerator;

$ics = $appointment->toIcs();
$ics = app(IcsGenerator::class)->forCollection($user->appointments()->get());

return response($appointment->toIcs(), 200, [
    'Content-Type' => 'text/calendar; charset=utf-8',
    'Content-Disposition' => 'attachment; filename="appointment.ics"',
]);
```

### Relationships

```php
$appointment->participants;  // HasMany<Participant>
$participant->appointment;   // BelongsTo<Appointment>
$participant->participant;   // MorphTo — the underlying entity (e.g. a User)
```

Both models use soft deletes, so deleting an appointment or participant retains the row with a
`deleted_at` timestamp.

### Events

| Event | Dispatched when |
|---|---|
| `RoundlyConsulting\Appointments\Events\AppointmentCreated` | an appointment is created |
| `RoundlyConsulting\Appointments\Events\AppointmentUpdated` | an appointment is updated |
| `RoundlyConsulting\Appointments\Events\AppointmentRescheduled` | an appointment is rescheduled (carries `$previousStartsAt`) |
| `RoundlyConsulting\Appointments\Events\AppointmentStatusChanged` | a status transition occurs (carries `$from` and `$to`) |
| `RoundlyConsulting\Appointments\Events\ParticipantCreated` | a participant is created |
| `RoundlyConsulting\Appointments\Events\ParticipantUpdated` | a participant is updated |
| `RoundlyConsulting\Appointments\Events\ParticipantDeleted` | a participant is deleted |

### Backward compatibility

The original `AppointmentsService` (`create()` / `addParticipantToAppointment()`) is retained
as a deprecated shim that delegates to the new actions. New code should use the facade,
builder, or action classes.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
