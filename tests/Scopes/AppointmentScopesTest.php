<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;

beforeEach(function (): void {
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('filters upcoming and past appointments', function (): void {
    Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-05 09:00')]);
    Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-06-20 09:00')]);

    expect(Appointment::query()->upcoming()->count())->toBe(1)
        ->and(Appointment::query()->past()->count())->toBe(1);
});

it('filters appointments between two dates', function (): void {
    Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-03 09:00')]);
    Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-20 09:00')]);

    $between = Appointment::query()->between(
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-07-10'),
    )->count();

    expect($between)->toBe(1);
});

it('filters overlapping appointments', function (): void {
    Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00'),
        'duration_minutes' => 60,
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00'),
    ]);

    $overlap = Appointment::query()->overlapping(
        CarbonImmutable::parse('2026-07-01 09:30'),
        CarbonImmutable::parse('2026-07-01 10:30'),
    )->count();

    $touching = Appointment::query()->overlapping(
        CarbonImmutable::parse('2026-07-01 10:00'),
        CarbonImmutable::parse('2026-07-01 11:00'),
    )->count();

    expect($overlap)->toBe(1)->and($touching)->toBe(0);
});

it('filters by status', function (): void {
    Appointment::factory()->withStatus(Status::Confirmed)->create();
    Appointment::factory()->withStatus(Status::Cancelled)->create();

    expect(Appointment::query()->withStatus(Status::Confirmed)->count())->toBe(1)
        ->and(Appointment::query()->withStatus(Status::Confirmed, Status::Cancelled)->count())->toBe(2);
});

it('filters and orders by participant', function (): void {
    $user = User::create();
    $other = User::create();

    $mine = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-10 09:00')]);
    $earlier = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-02 09:00')]);
    $theirs = Appointment::factory()->create();

    app(AttachParticipantAction::class)->execute($mine, new ParticipantData($user));
    app(AttachParticipantAction::class)->execute($earlier, new ParticipantData($user));
    app(AttachParticipantAction::class)->execute($theirs, new ParticipantData($other));

    $results = Appointment::query()->forParticipant($user)->ordered()->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->is($earlier))->toBeTrue();
});
