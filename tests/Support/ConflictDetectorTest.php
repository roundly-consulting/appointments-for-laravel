<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Appointments\Tests\Models\User;

function book(User $user, string $start, int $minutes, Status $status = Status::Pending): Appointment
{
    $startsAt = CarbonImmutable::parse($start);

    $appointment = Appointment::factory()->withStatus($status)->create([
        'starts_at' => $startsAt,
        'duration_minutes' => $minutes,
        'ends_at' => $startsAt->addMinutes($minutes),
    ]);

    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($user));

    return $appointment;
}

beforeEach(function (): void {
    $this->detector = app(ConflictDetector::class);
    $this->user = User::create();
});

it('detects an overlapping booking', function (): void {
    book($this->user, '2026-07-01 09:00', 60);

    $conflicts = $this->detector->forParticipant(
        $this->user,
        CarbonImmutable::parse('2026-07-01 09:30'),
        CarbonImmutable::parse('2026-07-01 10:30'),
    );

    expect($conflicts)->toHaveCount(1)
        ->and($this->detector->hasConflict(
            $this->user,
            CarbonImmutable::parse('2026-07-01 09:30'),
            CarbonImmutable::parse('2026-07-01 10:30'),
        ))->toBeTrue();
});

it('treats touching ranges as non-overlapping', function (): void {
    book($this->user, '2026-07-01 09:00', 60);

    $conflicts = $this->detector->forParticipant(
        $this->user,
        CarbonImmutable::parse('2026-07-01 10:00'),
        CarbonImmutable::parse('2026-07-01 11:00'),
    );

    expect($conflicts)->toBeEmpty();
});

it('ignores cancelled and declined appointments', function (): void {
    book($this->user, '2026-07-01 09:00', 60, Status::Cancelled);
    book($this->user, '2026-07-01 09:00', 60, Status::Declined);

    $conflicts = $this->detector->forParticipant(
        $this->user,
        CarbonImmutable::parse('2026-07-01 09:15'),
        CarbonImmutable::parse('2026-07-01 09:45'),
    );

    expect($conflicts)->toBeEmpty();
});

it('can exclude a given appointment from the conflict set', function (): void {
    $appointment = book($this->user, '2026-07-01 09:00', 60);

    $conflicts = $this->detector->forParticipant(
        $this->user,
        CarbonImmutable::parse('2026-07-01 09:15'),
        CarbonImmutable::parse('2026-07-01 09:45'),
        ignore: $appointment,
    );

    expect($conflicts)->toBeEmpty();
});

it('does not flag a different participant', function (): void {
    book($this->user, '2026-07-01 09:00', 60);
    $other = User::create();

    $conflicts = $this->detector->forParticipant(
        $other,
        CarbonImmutable::parse('2026-07-01 09:15'),
        CarbonImmutable::parse('2026-07-01 09:45'),
    );

    expect($conflicts)->toBeEmpty();
});
