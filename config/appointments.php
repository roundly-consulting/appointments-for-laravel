<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

return [
    /*
    |--------------------------------------------------------------------------
    | Appointment model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to represent an appointment. Override this with
    | your own model (extending the package model) to customise behaviour.
    |
    */
    'model' => Appointment::class,

    /*
    |--------------------------------------------------------------------------
    | Participant model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to represent an appointment participant.
    |
    */
    'participant' => Participant::class,

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | The database tables the package migrations create and the models read
    | from. Override these if they clash with existing tables in your app.
    |
    */
    'table_names' => [
        'appointments' => 'appointments',
        'participants' => 'appointment_participants',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default timezone
    |--------------------------------------------------------------------------
    |
    | The timezone stored on an appointment when none is supplied. Leave null
    | to fall back to the host application's timezone (config('app.timezone')).
    |
    */
    'timezone' => null,

    /*
    |--------------------------------------------------------------------------
    | Default duration (minutes)
    |--------------------------------------------------------------------------
    |
    | Applied when an appointment is created without an explicit duration or
    | end time, to derive ends_at from starts_at.
    |
    */
    'default_duration_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Prevent conflicts
    |--------------------------------------------------------------------------
    |
    | When true, creating or rescheduling an appointment throws a
    | SchedulingConflictException if a participant is already booked in an
    | overlapping slot. Opt-in; can also be enabled per call on the builder.
    |
    */
    'prevent_conflicts' => false,

    /*
    |--------------------------------------------------------------------------
    | Recurrence
    |--------------------------------------------------------------------------
    |
    | Guards the recurrence expander against runaway occurrence counts.
    |
    */
    'recurrence' => [
        'max_occurrences' => 365,
    ],
];
