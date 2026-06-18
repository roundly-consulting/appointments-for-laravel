<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('defaults role and meta to null', function (): void {
    $user = User::create();
    $data = new ParticipantData($user);

    expect($data)
        ->participant->toBe($user)
        ->role->toBeNull()
        ->meta->toBeNull();
});

it('keeps the role and meta it is given', function (): void {
    $user = User::create();
    $data = new ParticipantData($user, ParticipantRole::Organiser, ['is_host' => true]);

    expect($data)
        ->role->toBe(ParticipantRole::Organiser)
        ->meta->toBe(['is_host' => true]);
});
