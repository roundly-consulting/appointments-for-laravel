<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\ScheduleRecurringAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Tests\Models\User;

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
