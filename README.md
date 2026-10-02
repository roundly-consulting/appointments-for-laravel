<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/appointments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel">
    <img src="art/hero.png" alt="Appointments for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/appointments-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/appointments-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/appointments-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/appointments-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/appointments-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/appointments-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Appointments for Laravel

A scheduling toolkit for Laravel: create appointments with durations, end times, and time
zones; attach participants of any Eloquent model via a polymorphic relationship; detect
double-bookings; drive a guarded status lifecycle; generate recurring series; and export
standards-compliant calendar (`.ics`) files. Built on a native service provider, it also
builds on the roundly-consulting provider packages for venue geolocation, guest contacts,
post-visit reviews, and a booking-approval workflow (see **Integrates with** below).

## Requirements

- PHP `^8.4`
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

The package does **not** load its migrations automatically — publishing them is required, and
`php artisan migrate` alone will not create the tables. Once published, the migrations are yours:
they live in your `database/migrations` directory and run in the order they were published
(appointments, then participants, then the location columns).

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="appointments-config"
```

Status, role, and frequency labels come from the `enums-for-laravel` `Helpers` trait
(`readable()` / `labels()` / `options()`), so there are no language files to publish — override
labels through the enums translation seam instead.

## Configuration

The published config file lives at `config/appointments.php`:

```php
return [
    'model' => Appointment::class,
    'participant' => Participant::class,
    'key_type' => env('APPOINTMENTS_KEY_TYPE', 'bigint'),
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
    'reviews' => [
        'verified_attendance_resolver' => DatabaseVerifiedAttendanceResolver::class,
        'require_verified_attendance' => false,
    ],
    'approvals' => [
        'enforce_transitions' => false,
    ],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Appointments\Models\Appointment` | The Eloquent model used for appointments. Point it at your own subclass to customise behaviour. |
| `participant` | `class-string` | `RoundlyConsulting\Appointments\Models\Participant` | The Eloquent model used for appointment participants. |
| `key_type` | `string` | `bigint` (`APPOINTMENTS_KEY_TYPE`) | Key type of the polymorphic `participant_id` column the participants migration creates: `bigint`, `uuid` or `ulid`. Match the primary keys of the models that take part (they must share one type); set it before running the migrations. Any other value falls back to `bigint`. |
| `table_names.appointments` | `string` | `appointments` | Table the migrations create and the `Appointment` model reads and writes. Set it before running the migrations. |
| `table_names.participants` | `string` | `appointment_participants` | Table the migrations create and the `Participant` model reads and writes. Set it before running the migrations. |
| `timezone` | `?string` | `null` | The zone stored on a new appointment when none is given, and the zone a wall-clock string without an offset is read in. `null` uses `config('app.timezone')`. Each appointment keeps the zone it was booked in, so changing this later does not re-time existing appointments. |
| `default_duration_minutes` | `int` | `60` | Duration applied when none is supplied, used to derive `ends_at` from `starts_at`. |
| `prevent_conflicts` | `bool` | `false` | When `true`, creating, rescheduling or adding a participant throws on an overlapping booking for a participant. Can also be enabled per call. |
| `recurrence.max_occurrences` | `int` | `365` | Hard cap on the number of occurrences a recurring series may expand to. |
| `reviews.verified_attendance_resolver` | `class-string` | `DatabaseVerifiedAttendanceResolver` | Resolver that decides whether a review is verified (default: author is a participant of a Completed appointment). |
| `reviews.require_verified_attendance` | `bool` | `false` | When `true`, `review()` throws for an unverified author instead of storing an unverified review. |
| `approvals.enforce_transitions` | `bool` | `false` | When `true`, the approval status-sync listener respects the appointment transition matrix (a mapped-but-illegal move is skipped). |

The effective configuration is summarised in Laravel's `about` command:

```bash
php artisan about --only=appointments
```

## Usage

Everything goes through the `Appointments` facade. The same API is available by injecting
`AppointmentManager` (see [Without the facade](#without-the-facade)), and every write is also a
plain action class.

### Scheduling an appointment

```php
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Facades\Appointments;

$appointment = Appointments::schedule('Project kickoff')
    ->startingAt('2026-07-01 17:30', timezone: 'Europe/Bratislava')
    ->lasting(90)                       // minutes; or ->until('2026-07-01 19:00')
    ->describedAs('The best chicken wings ever!')
    ->withMeta(['location' => 'My house'])
    ->withParticipant($host, ParticipantRole::Organiser, ['is_host' => true])
    ->withParticipant($guest)
    ->preventConflicts()                // opt-in double-booking guard for this call
    ->create();
```

Or hand over a typed DTO:

```php
use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;

$appointment = Appointments::create(new AppointmentData(
    name: 'Project kickoff',
    startsAt: CarbonImmutable::parse('2026-07-01 17:30'),
    durationMinutes: 90,
    participants: [
        new ParticipantData($host, ParticipantRole::Organiser),
        new ParticipantData($guest),
    ],
));
```

A booking is written in one transaction, and its events fire only once that commits. So a refused
or failed booking — a conflict, a participant listed twice (`DuplicateParticipantException`), an
invalid contact, an unknown approval workflow — leaves no row and fires no event.

`starts_at` and `ends_at` always hold UTC, whatever `app.timezone` is, and read back as UTC
`CarbonImmutable`s. A Carbon you pass keeps its instant. A wall-clock string without an offset is
read in the `timezone:` you give, else `appointments.timezone`, else `app.timezone`. That zone is
stored on the appointment and drives local display:

```php
$appointment->startsAtLocal();   // CarbonImmutable in the appointment's timezone
$appointment->endsAtLocal();
$appointment->duration();        // CarbonInterval
$appointment->durationInMinutes();
```

### Duration and end time

Provide a duration (`lasting()` / `durationMinutes`) or an explicit end (`until()`). When only
one is given the other is derived; when neither is given the `default_duration_minutes` config value
is used. `until()` is measured against the start whether you call it before or after
`startingAt()`, and whichever of `lasting()` / `until()` comes last wins. A duration under one
minute, or an end that does not come after the start, throws `InvalidScheduleException` before
anything is written. `reschedule()` applies the same rule to `durationMinutes`.

### Working with one appointment

`Appointments::for($appointment)` scopes every operation to one booking:

```php
use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Enums\Status;

$booking = Appointments::for($appointment);

$booking->reschedule(CarbonImmutable::parse('2026-07-02 10:00'));            // keeps the duration
$booking->reschedule($newStart, durationMinutes: 30, preventConflicts: true); // fires AppointmentRescheduled

$booking->confirm();                  // or cancel(), complete(), decline(), markNoShow()
$booking->transition(Status::Confirmed);

$booking->participants()->add($user, ParticipantRole::Attendee, meta: ['seat' => 4]);
$booking->ics();                      // this appointment as a one-event calendar
```

### Status lifecycle

Status is a guarded state machine. Illegal transitions throw
`InvalidStatusTransitionException`; legal ones persist and fire `AppointmentStatusChanged`.
The model carries the same shortcuts, and they go through the facade's manager too (so
`Appointments::fake()` records them):

```php
use RoundlyConsulting\Appointments\Enums\Status;

Appointments::for($appointment)->confirm();   // pending → confirmed
$appointment->confirm();                       // the same, from the model
$appointment->cancel();     // pending/confirmed → cancelled
$appointment->complete();   // confirmed → completed
$appointment->decline();    // pending → declined
$appointment->markNoShow(); // confirmed → no_show
$appointment->transitionTo(Status::Confirmed);

$appointment->status->label();  // "Confirmed" (from the enums Helpers trait)
$appointment->status->color();  // a colour token for UI badges
Status::options();               // value/label option DTOs for a <select>
Status::validationRule();        // "in:pending,confirmed,cancelled,completed,declined,no_show"
```

Allowed transitions:

| From | To |
|---|---|
| `pending` | `confirmed`, `declined`, `cancelled` |
| `confirmed` | `completed`, `cancelled`, `no_show` |
| `cancelled`, `completed`, `declined`, `no_show` | _(final)_ |

### Participants

Any Eloquent model can take part — a user, a contact, a room:

```php
$participants = Appointments::for($appointment)->participants();

$row = $participants->add($user, ParticipantRole::Attendee, meta: ['seat' => 4]);
$participants->add($room, preventConflicts: true);   // refuse if the room is booked then

$participants->has($user);    // true
$participants->all();         // the participant rows (role, meta), models eager-loaded
$participants->remove($user); // by model…
$participants->remove($row);  // …or by one of this appointment's participant rows
```

- Adding a model that already takes part throws `DuplicateParticipantException`.
- With `preventConflicts: true` (or `prevent_conflicts` in config), adding a model that is booked
  elsewhere in the appointment's window throws `SchedulingConflictException`.
- Removing a model that does not take part — or a participant row that belongs to **another**
  appointment — throws `ParticipantNotFoundException`. Nothing is deleted.
- Removal soft-deletes the row (`ParticipantDeleted`). Adding the same model again restores that
  row with the new role and meta (`ParticipantUpdated`); a first-time add fires
  `ParticipantCreated`.

### Availability and conflicts

```php
Appointments::isAvailable($user, $start, $end);              // bool
Appointments::conflicts($user, $start, $end);                // Collection<Appointment>
Appointments::conflicts($user, $start, $end, ignore: $appt); // leave one booking out
```

`$start` and `$end` may be Carbons in any timezone; they are compared as the instants they are.

With `prevent_conflicts` enabled (globally or per call), creating, rescheduling or adding a
participant that overlaps an existing booking throws `SchedulingConflictException`, which carries
the conflicting appointments. Touching ranges (one ends exactly when the next begins) do not
conflict; cancelled and declined appointments are ignored. The check holds under concurrency: it
runs in the transaction that writes, after locking each participant's row (and, for a reschedule or
an added participant, the appointment's row), so two simultaneous bookings of one person cannot both
pass it. (SQLite has no row locks; it serializes writers instead.)

### Recurring appointments

```php
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;

$series = Appointments::schedule('Weekly standup')
    ->startingAt('2026-07-06 09:00')
    ->lasting(30)
    ->recurring(new RecurrenceData(Frequency::Weekly, count: 8, byWeekday: [1])) // Mondays
    ->createRecurring();

$series = Appointments::createRecurring($appointmentData, new RecurrenceData(Frequency::Daily, count: 5));

// Preview the start times a rule produces, without writing anything:
Appointments::occurrences(CarbonImmutable::parse('2026-07-06 09:00'), $rule); // list<CarbonImmutable>
```

Each occurrence is materialised as its own appointment, linked by a shared `recurrence_group`
UUID, and carries everything a single `create()` would — location, coordinates, guest contacts
and its own approval request. The series is all-or-nothing: if any occurrence fails (e.g. a
`SchedulingConflictException` under `preventConflicts()`), none of it is kept, and — since events
wait for the commit — no `AppointmentCreated` (or participant event) fires for any of it.
`createRecurring()` falls back to the DTO's own `recurrence`; with no rule at all it creates the
single appointment.

Recurrence supports `daily`/`weekly`/`monthly` frequencies, an `interval` (every Nth day, week or
month), a `count` or `until` bound, and `byWeekday` (ISO 1 = Monday … 7 = Sunday). With
`byWeekday`, every listed weekday of each active period occurs — e.g.
`new RecurrenceData(Frequency::Weekly, interval: 2, byWeekday: [1, 3])` is Monday and Wednesday of
every other week, and `Frequency::Monthly` with `byWeekday: [1]` is every Monday of each active
month. An `until` at midnight is read as a date and includes that day. The rule is expanded in the
appointment's timezone, so a weekly 09:00 stays 09:00 local across a daylight-saving change. An
`interval` or `count` below one, or a weekday outside 1–7, throws `InvalidRecurrenceException`.
Expansion is capped by `recurrence.max_occurrences`.

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

The time scopes compare against the UTC columns, so their Carbon arguments may be in any timezone.

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

Generate RFC 5545 calendar text for one appointment or a whole feed — the package stays
HTTP-free, so you decide how to deliver it. Each event's `UID` is the appointment's stored random
`uuid` at your `app.url` host (`…@example.com`), so it stays unique across apps and survives a
re-seeded database:

```php
$ics = Appointments::for($appointment)->ics();                 // or $appointment->toIcs()
$feed = Appointments::ics($user->appointments()->upcoming()->get()); // one VCALENDAR, many VEVENTs

return response($feed, 200, [
    'Content-Type' => 'text/calendar; charset=utf-8',
    'Content-Disposition' => 'attachment; filename="appointments.ics"',
]);
```

Text values are escaped per RFC 5545 §3.3.11 — any line break (a textarea's CRLF, a lone CR or
LF) becomes one `\n`, and `\`, `;` and `,` are escaped — and long lines fold at 75 octets without
splitting a multi-byte UTF-8 character.

### Without the facade

The facade is sugar over `AppointmentManager`. Inject it for the identical API:

```php
use RoundlyConsulting\Appointments\AppointmentManager;

final class BookingController
{
    public function __construct(private AppointmentManager $appointments) {}

    public function store(): void
    {
        $appointment = $this->appointments->schedule('Consultation')->startingAt(now()->addDay())->create();
        $this->appointments->for($appointment)->participants()->add(auth()->user());
    }
}
```

Or run an action directly (queued jobs, your own actions):

```php
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\Actions\DetachParticipantAction;
use RoundlyConsulting\Appointments\Actions\RescheduleAppointmentAction;
use RoundlyConsulting\Appointments\Actions\ScheduleRecurringAppointmentAction;
use RoundlyConsulting\Appointments\Actions\TransitionAppointmentAction;

$appointment = app(CreateAppointmentAction::class)->execute($appointmentData);
app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($user), preventConflicts: true);
app(DetachParticipantAction::class)->execute($appointment, $user);
app(RescheduleAppointmentAction::class)->execute($appointment, $newStart, durationMinutes: 45);
app(TransitionAppointmentAction::class)->execute($appointment, Status::Confirmed);
app(ScheduleRecurringAppointmentAction::class)->execute($appointmentData, $rule);
```

Actions called directly bypass `Appointments::fake()`; the facade, an injected manager and the
model shortcuts do not.

### Facade reference

| Method | Returns |
|---|---|
| `schedule(string $name)` | `AppointmentBuilder` — `startingAt/lasting/until/describedAs/withMeta/withStatus/withParticipant/located/at/venue/withContactEmail/withContactPhone/requireApprovalFrom/approvalRule/approvalQuorum/approvalStages/rejectOnStageRejection/approvalWorkflow/approvalStageApprovers/recurring/preventConflicts` → `create()` / `createRecurring()` |
| `create(AppointmentData $data)` | `Appointment` |
| `createRecurring(AppointmentData $data, ?RecurrenceData $rule = null)` | `Collection<int, Appointment>` |
| `for(Appointment $appointment)` | `AppointmentHandle` — `reschedule()`, `transition()`, `confirm()`, `cancel()`, `complete()`, `decline()`, `markNoShow()`, `participants()`, `ics()` |
| `for($appointment)->participants()` | `AppointmentParticipants` — `add()`, `remove()`, `has()`, `all()` |
| `conflicts(Model $participant, $start, $end, ?Appointment $ignore = null)` | `Collection<int, Appointment>` |
| `isAvailable(Model $participant, $start, $end, ?Appointment $ignore = null)` | `bool` |
| `ics(iterable $appointments)` | `string` |
| `occurrences(CarbonInterface $start, RecurrenceData $rule)` | `list<CarbonImmutable>` |
| `fake()` | `AppointmentsFake` |

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
| `RoundlyConsulting\Appointments\Events\ParticipantCreated` | a participant is added for the first time |
| `RoundlyConsulting\Appointments\Events\ParticipantUpdated` | a participant row is updated, or a removed participant is added again |
| `RoundlyConsulting\Appointments\Events\ParticipantDeleted` | a participant is removed |

Every package event implements `ShouldDispatchAfterCommit`: inside a database transaction it fires
once that commits, and never for a write that rolls back.

## Integrates with

Appointments hard-requires six roundly-consulting packages and wires them into the
bundled `Appointment` model. If you swap the model via `config('appointments.model')`, extend
the packaged model — the traits (`HasLocation`, `HasContacts`, `HasReviews`, `RequiresApproval`)
come with it.

| Provider | What it adds |
|---|---|
| `package-toolkit-for-laravel` | The package's service provider, publish groups and `php artisan about --only=appointments` section, plus model resolution from config. |
| `enums-for-laravel` | `Status` / `ParticipantRole` / `Frequency` gain `values()`/`labels()`/`options()`/`validationRule()`/`readable()`. |
| `geolocation-for-laravel` | Venue coordinates, a `withinRadius` scope, a distance helper, and an ICS `GEO` line. |
| `contacts-for-laravel` | Guest/booking email & phone contacts and ICS `ATTENDEE`/`ORGANIZER` `mailto:` lines. |
| `approvals-for-laravel` | A booking-approval workflow that drives the appointment status. |
| `reviews-for-laravel` | Post-visit reviews and rating summaries, gated by verified attendance. |

### Venue location (geolocation)

```php
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

$appointment = Appointments::schedule('Clinic visit')
    ->startingAt('2026-07-01 09:00')
    ->located(51.5074, -0.1278, 'Clinic A')   // latitude, longitude, venue name
    ->create();

$appointment->coordinates;                     // Coordinates value object (or null)
$appointment->distanceFrom(new Coordinates(48.8566, 2.3522)); // kilometres, or null

Appointment::query()->withinRadius(new Coordinates(51.5074, -0.1278), 10)->get();
```

The ICS export prefers the first-class `location` column (falling back to `meta.location`) and
emits a `GEO:lat;lng` line when coordinates are present.

### Guest contacts (contacts)

```php
$appointment = Appointments::schedule('Guest booking')
    ->startingAt('2026-07-01 09:00')
    ->withContactEmail('guest@example.com')
    ->withContactPhone('+441234567890')
    ->create();

$appointment->primaryEmail()?->value;          // "guest@example.com"
$appointment->addEmail('other@example.com');
```

A participant that exposes a primary email is exported as `ATTENDEE;CN="…":mailto:…`, and the
appointment's booking contact becomes the calendar `ORGANIZER`. A participant with no email is
left out of the `.ics`: an `ATTENDEE` must be a calendar address (in practice `mailto:`), and
Google Calendar, Apple Calendar and Outlook have nothing to act on without one.

### Booking approval workflow (approvals)

Opt a booking into confirmation sign-off. The appointment is created `pending` and a
`SyncAppointmentStatusFromApproval` listener moves it as the approval request resolves:
Approved → `confirmed`, Rejected → `declined`, Cancelled/Expired → `cancelled`. A final status
(`cancelled`, `declined`, `completed`, `no_show`) is never left: approving the still-open request of
a booking the customer already cancelled changes nothing. Only the named approvers can decide.

```php
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Facades\Approvals;

$appointment = Appointments::schedule('Booking request')
    ->startingAt('2026-07-01 09:00')
    ->requireApprovalFrom([$organiser, $clinician], ApprovalRule::Quorum, quorum: 1)
    ->create();

Approvals::for($appointment)->as($organiser)->approve();   // → confirmed
```

Staged pipelines and named workflow presets are supported too:

```php
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;

Appointments::schedule('Two-desk booking')
    ->startingAt('2026-07-01 09:00')
    ->approvalStages([
        new StageDefinition([$reception]),
        new StageDefinition([$clinician]),
    ])
    ->create();

Appointments::schedule('Preset booking')
    ->startingAt('2026-07-01 09:00')
    ->approvalWorkflow('clinic')                 // config('approvals.workflows.clinic')
    ->approvalStageApprovers([[$reception], [$clinician]])
    ->create();
```

Approver models use the approvals `GivesApprovals` trait. The `appointments:expire-approvals`
command runs the approvals engine's expiry, which is app-wide: it lapses every expired approval
decision and request (other subjects' included), and the appointments whose requests resolve move
to `cancelled`.

### Post-visit reviews (reviews)

Appointments are reviewable. A review by a participant of a Completed appointment is stamped
verified; anyone else stays unverified (or is rejected when
`reviews.require_verified_attendance` is on). Aggregates count approved reviews only.

```php
$review = $appointment->review($attendee)->rating(5)->content('Great')->create();

$appointment->averageRating();
$appointment->ratingSummary();          // RatingSummary DTO
$appointment->approvedReviewsCount();
```

Author models use the reviews `CanReview` trait.

## Testing

### Faking appointments in your app's tests

`Appointments::fake()` swaps in a recording `AppointmentsFake` — behind the facade **and** in the
container, so an injected `AppointmentManager` is faked too. Operations still run against the
database (availability checks, `participants()` reads and the package events keep working);
every write is recorded, whether it came through the facade, an injected manager, the builder,
a `for()` handle or a model shortcut such as `$appointment->cancel()`.

```php
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;

$fake = Appointments::fake();

$this->post('/bookings', [...]);

$fake->assertScheduled(fn (Appointment $appointment) => $appointment->name === 'Consultation');
$fake->assertTransitioned(fn (Appointment $appointment, Status $to, Status $from) => $to === Status::Cancelled);
$fake->assertRescheduled(fn (Appointment $appointment, $previousStartsAt) => true);
$fake->assertParticipantAdded(fn (Appointment $appointment, $participant, $row) => $participant->is($user));
$fake->assertParticipantRemoved(fn (Appointment $appointment, $participant) => $participant->is($user));

$fake->assertNothingScheduled();
$fake->assertNothingRescheduled();
$fake->assertNothingTransitioned();
$fake->assertNoParticipantAdded();
$fake->assertNoParticipantRemoved();
```

Every assertion also works statically (`Appointments::assertScheduled()`). Participants listed on
a **new** appointment are part of its scheduling; only `participants()->add()` counts as added.
Asking for the status an appointment already has is a no-op, so it is not recorded as a transition.

### The package's own suite

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
