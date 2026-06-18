<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('exposes the appointments a model takes part in', function (): void {
    $user = User::create();
    $other = User::create();

    $mine = Appointment::factory()->create();
    $theirs = Appointment::factory()->create();

    app(AttachParticipantAction::class)->execute($mine, new ParticipantData($user));
    app(AttachParticipantAction::class)->execute($theirs, new ParticipantData($other));

    expect($user->appointments()->get())->toHaveCount(1)
        ->and($user->appointments()->get()->first()->is($mine))->toBeTrue();
});

it('exposes the participant rows for a model', function (): void {
    $user = User::create();
    app(AttachParticipantAction::class)->execute(Appointment::factory()->create(), new ParticipantData($user));

    expect($user->appointmentParticipations())->toBeInstanceOf(MorphMany::class)
        ->and($user->appointmentParticipations()->count())->toBe(1);
});

it('lets the appointments relation chain scopes', function (): void {
    $user = User::create();
    app(AttachParticipantAction::class)->execute(
        Appointment::factory()->create(['starts_at' => now()->addWeek()]),
        new ParticipantData($user),
    );
    app(AttachParticipantAction::class)->execute(
        Appointment::factory()->create(['starts_at' => now()->subWeek()]),
        new ParticipantData($user),
    );

    expect($user->appointments()->upcoming()->count())->toBe(1);
});
