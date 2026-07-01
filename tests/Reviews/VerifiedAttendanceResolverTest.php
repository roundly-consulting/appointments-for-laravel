<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Reviews\DatabaseVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('is unverified when the subject is not an appointment', function (): void {
    $resolver = new DatabaseVerifiedAttendanceResolver;

    $author = User::create();
    $notAnAppointment = User::create();

    expect($resolver->verified($author, $notAnAppointment))->toBeFalse();
});
