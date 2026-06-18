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
];
