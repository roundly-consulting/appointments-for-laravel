<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\ScheduleRecurringAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentApprovalData;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

it('materialises one appointment per occurrence in a shared group', function (): void {
    $user = User::create();

    $appointments = app(ScheduleRecurringAppointmentAction::class)->execute(
        new AppointmentData(
            name: 'Weekly standup',
            startsAt: CarbonImmutable::parse('2026-07-06 09:00'),
            durationMinutes: 30,
            participants: [new ParticipantData($user)],
        ),
        new RecurrenceData(Frequency::Weekly, count: 4),
    );

    expect($appointments)->toHaveCount(4);

    $group = $appointments->first()->recurrence_group;

    expect($group)->not->toBeNull()
        ->and($appointments->pluck('recurrence_group')->unique())->toHaveCount(1)
        ->and($appointments->map(fn ($a) => $a->starts_at->format('Y-m-d'))->all())
        ->toBe(['2026-07-06', '2026-07-13', '2026-07-20', '2026-07-27']);

    expect($appointments->first()->participants)->toHaveCount(1);
});

it('carries location, coordinates, contacts and approval onto every occurrence', function (): void {
    $organiser = User::create();

    $series = Appointments::for('Physio')
        ->startingAt('2026-07-06 09:00')
        ->located(51.5074, -0.1278, 'Clinic A')
        ->withContactEmail('guest@example.com')
        ->requireApprovalFrom($organiser)
        ->recurring(new RecurrenceData(Frequency::Weekly, count: 3))
        ->createRecurring();

    expect($series)->toHaveCount(3);

    foreach ($series as $occurrence) {
        $fresh = $occurrence->fresh();

        expect($fresh?->location)->toBe('Clinic A')
            ->and($fresh?->coordinates?->latitude)->toBe(51.5074)
            ->and($fresh?->coordinates?->longitude)->toBe(-0.1278)
            ->and($fresh?->primaryEmail()?->value)->toBe('guest@example.com')
            ->and($fresh?->isPendingApproval())->toBeTrue()
            ->and($fresh?->status)->toBe(Status::Pending);
    }
});

it('builds each occurrence from every field of the single-appointment data', function (): void {
    // Drift guard: a field added to AppointmentData must reach recurring occurrences too.
    $data = new AppointmentData(
        name: 'Weekly',
        startsAt: CarbonImmutable::parse('2026-07-06 09:00'),
        durationMinutes: 45,
        timezone: 'Europe/Bratislava',
        description: 'Desc',
        meta: ['k' => 'v'],
        status: Status::Confirmed,
        participants: [new ParticipantData(User::create())],
        recurrence: new RecurrenceData(Frequency::Weekly, count: 2),
        preventConflicts: true,
        location: 'Room 1',
        coordinates: new Coordinates(1.5, 2.5),
        contacts: [new ContactData(ContactType::Email, 'a@example.com')],
        approval: new AppointmentApprovalData(approvers: [User::create()]),
    );

    $startsAt = CarbonImmutable::parse('2026-07-13 09:00');
    $occurrence = $data->forOccurrence($startsAt);

    foreach ((new ReflectionClass(AppointmentData::class))->getConstructor()?->getParameters() ?? [] as $parameter) {
        $name = $parameter->getName();

        $expected = match ($name) {
            'startsAt' => $startsAt,
            'recurrence' => null,
            default => $data->{$name},
        };

        expect($occurrence->{$name})->toBe($expected, "AppointmentData::\${$name} is not carried onto occurrences");
    }
});

it('rolls back the whole series when one occurrence conflicts', function (): void {
    $user = User::create();

    Appointments::for('Existing')
        ->startingAt('2026-07-13 09:00')
        ->lasting(60)
        ->withParticipant($user)
        ->create();

    $schedule = fn () => Appointments::for('Weekly')
        ->startingAt('2026-07-06 09:00')
        ->lasting(30)
        ->withParticipant($user)
        ->withContactEmail('guest@example.com')
        ->preventConflicts()
        ->recurring(new RecurrenceData(Frequency::Weekly, count: 3))
        ->createRecurring();

    expect($schedule)->toThrow(SchedulingConflictException::class);

    // The first week was free, but a half-created series must not be left behind.
    expect(Appointment::query()->where('name', 'Weekly')->count())->toBe(0);
});
