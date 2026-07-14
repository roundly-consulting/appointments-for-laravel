<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Appointments\Support\ParticipantModel;
use RoundlyConsulting\Appointments\Tests\Models\CustomAppointment;
use RoundlyConsulting\Appointments\Tests\Models\CustomParticipant;
use RoundlyConsulting\Appointments\Tests\Models\User;

it('resolves the packaged models by default', function (): void {
    expect(AppointmentModel::class())->toBe(Appointment::class)
        ->and(ParticipantModel::class())->toBe(Participant::class);
});

it('resolves host models configured on the package', function (): void {
    config()->set('appointments.model', CustomAppointment::class);
    config()->set('appointments.participant', CustomParticipant::class);

    expect(AppointmentModel::class())->toBe(CustomAppointment::class)
        ->and(ParticipantModel::class())->toBe(CustomParticipant::class);
});

it('falls back to the packaged models when the configured class is not one', function (): void {
    config()->set('appointments.model', User::class);
    config()->set('appointments.participant', User::class);

    expect(AppointmentModel::class())->toBe(Appointment::class)
        ->and(ParticipantModel::class())->toBe(Participant::class);
});

it('creates appointments and participants through the configured host models', function (): void {
    config()->set('appointments.model', CustomAppointment::class);
    config()->set('appointments.participant', CustomParticipant::class);

    $user = User::create();

    $appointment = app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'Kick-off',
        startsAt: CarbonImmutable::parse('2026-07-14 09:00:00'),
        participants: [new ParticipantData(participant: $user)],
    ));

    expect($appointment)->toBeInstanceOf(CustomAppointment::class)
        ->and($appointment->participants()->first())->toBeInstanceOf(CustomParticipant::class)
        ->and($user->appointmentParticipations()->first())->toBeInstanceOf(CustomParticipant::class)
        ->and($user->appointments()->first())->toBeInstanceOf(CustomAppointment::class);
});

it('detects conflicts through the configured host model', function (): void {
    config()->set('appointments.model', CustomAppointment::class);
    config()->set('appointments.participant', CustomParticipant::class);

    $user = User::create();

    app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'Kick-off',
        startsAt: CarbonImmutable::parse('2026-07-14 09:00:00'),
        durationMinutes: 60,
        participants: [new ParticipantData(participant: $user)],
    ));

    $conflicts = app(ConflictDetector::class)->forParticipant(
        $user,
        CarbonImmutable::parse('2026-07-14 09:30:00'),
        CarbonImmutable::parse('2026-07-14 10:30:00'),
    );

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts->first())->toBeInstanceOf(CustomAppointment::class);
});

it('relates a participant back to the configured appointment model', function (): void {
    config()->set('appointments.model', CustomAppointment::class);
    config()->set('appointments.participant', CustomParticipant::class);

    $user = User::create();

    $appointment = app(CreateAppointmentAction::class)->execute(new AppointmentData(
        name: 'Kick-off',
        startsAt: CarbonImmutable::parse('2026-07-14 09:00:00'),
        participants: [new ParticipantData(participant: $user)],
    ));

    $participant = $appointment->participants()->firstOrFail();

    expect($participant->appointment)->toBeInstanceOf(CustomAppointment::class);
});
