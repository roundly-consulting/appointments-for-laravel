<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Reviews\DatabaseVerifiedAttendanceResolver;

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
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic participant column. Use "uuid" or
    | "ulid" when the models that column points at use UUID/ULID primary keys,
    | otherwise leave it as "bigint". Your morph targets must share one key type;
    | set this to match. Any unrecognized value falls back to "bigint".
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('APPOINTMENTS_KEY_TYPE', 'bigint'),

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
    | When true, creating or rescheduling an appointment, or adding a participant
    | to one, throws a SchedulingConflictException if a participant is already
    | booked in an overlapping slot. Opt-in; can also be enabled per call.
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

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    |
    | Post-appointment reviews are provided by reviews-for-laravel. The resolver
    | decides whether a review is "verified" — by default an author is verified
    | only when they are a participant of a Completed appointment. Set
    | "require_verified_attendance" to reject reviews from unverified authors
    | outright instead of merely marking them unverified.
    |
    */
    'reviews' => [
        'verified_attendance_resolver' => DatabaseVerifiedAttendanceResolver::class,
        'require_verified_attendance' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Approvals
    |--------------------------------------------------------------------------
    |
    | Booking-request sign-off is provided by approvals-for-laravel. An
    | appointment opted into a workflow (via requireApprovalFrom()) starts
    | Pending and is confirmed/declined/cancelled as its approval request
    | resolves. "enforce_transitions" keeps the status-sync listener inside the
    | appointment's own transition matrix; disable it to force the mapped state.
    |
    */
    'approvals' => [
        'enforce_transitions' => false,
    ],
];
