# Appointments for Laravel

Manage appointments between any Eloquent entities — schedule an appointment, attach
participants of any model type via a polymorphic relationship, store arbitrary metadata, and
react to lifecycle events.

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

The package's migrations are auto-discovered, so publishing them is only needed when you want
to customise the schema.

## Configuration

The published config file lives at `config/appointments.php`:

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

return [
    'model' => Appointment::class,

    'participant' => Participant::class,
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Appointments\Models\Appointment` | The Eloquent model used for appointments. Point it at your own subclass to customise behaviour. |
| `participant` | `class-string` | `RoundlyConsulting\Appointments\Models\Participant` | The Eloquent model used for appointment participants. |

## Usage

### Creating an appointment

Resolve `AppointmentsService` from the container and call `create`. Participants may be passed
as a plain model, or as a `[model, meta]` tuple to attach per-participant metadata.

```php
use Illuminate\Support\Carbon;
use RoundlyConsulting\Appointments\AppointmentsService;

$appointments = resolve(AppointmentsService::class);

$appointment = $appointments->create(
    name: 'Awesome dinner',
    appointmentAt: Carbon::parse('2026-07-01 17:30'),
    description: 'The best chicken wings ever!',
    meta: collect([
        'location' => 'My house',
        'bring' => 'Beer',
    ]),
    participants: collect([
        [$host, ['is_host' => true]], // model + per-participant meta
        $guest,                       // model only
    ]),
);
```

### Adding a participant to an existing appointment

```php
$appointments->addParticipantToAppointment(
    appointment: $appointment,
    participant: $user,
    meta: collect(['role' => 'Developer']),
);
```

### The Status enum

Appointments carry a `status` cast to `RoundlyConsulting\Appointments\Enums\Status`:

```php
use RoundlyConsulting\Appointments\Enums\Status;

Status::New;       // default for new appointments
Status::Accepted;
Status::Rejected;
Status::Canceled;
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

Models dispatch package events you can listen to:

| Event | Dispatched when |
|---|---|
| `RoundlyConsulting\Appointments\Events\AppointmentCreated` | an appointment is created |
| `RoundlyConsulting\Appointments\Events\AppointmentUpdated` | an appointment is updated |
| `RoundlyConsulting\Appointments\Events\ParticipantCreated` | a participant is created |
| `RoundlyConsulting\Appointments\Events\ParticipantUpdated` | a participant is updated |
| `RoundlyConsulting\Appointments\Events\ParticipantDeleted` | a participant is deleted |

Each appointment event exposes a public `$appointment`; each participant event a public
`$participant`. Listen to them to notify participants of changes.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
