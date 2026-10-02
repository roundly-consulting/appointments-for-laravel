<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\Approval;

it('lapses expired appointment-approval decisions', function (): void {
    $organiser = User::create();

    $appointment = Appointments::schedule('Expiring booking')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    // A pending decision that expired an hour ago.
    Approvals::for($appointment)->as($organiser)->expiringAt(CarbonImmutable::now()->subHour())->ask();

    $this->artisan('appointments:expire-approvals')
        ->expectsOutputToContain('Lapsed 1 expired approval decision(s).')
        ->assertSuccessful();

    expect(Approval::query()->where('status', ApprovalStatus::Expired)->count())->toBe(1);
});

it('leaves an unrelated subject\'s overdue approval pending', function (): void {
    $organiser = User::create();
    $document = User::create();

    $appointment = Appointments::schedule('Expiring booking')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    Approvals::for($appointment)->as($organiser)->expiringAt(CarbonImmutable::now()->subHour())->ask();
    // Another subject type's decision, just as overdue — not the command's to lapse.
    Approvals::for($document)->as($organiser)->expiringAt(CarbonImmutable::now()->subHour())->ask();

    $this->artisan('appointments:expire-approvals')
        ->expectsOutputToContain('Lapsed 1 expired approval decision(s).')
        ->assertSuccessful();

    expect(Approval::query()->where('approvable_type', $document->getMorphClass())->sole()->status)
        ->toBe(ApprovalStatus::Pending)
        ->and(Approval::query()->where('approvable_type', $appointment->getMorphClass())->sole()->status)
        ->toBe(ApprovalStatus::Expired);
});

it('scopes the expiry to the appointment morph alias', function (): void {
    Relation::morphMap(['appointment' => Appointment::class]);

    try {
        $organiser = User::create();
        $document = User::create();

        $appointment = Appointments::schedule('Aliased booking')
            ->startingAt('2026-07-01 09:00')
            ->requireApprovalFrom($organiser)
            ->create();

        Approvals::for($appointment)->as($organiser)->expiringAt(CarbonImmutable::now()->subHour())->ask();
        Approvals::for($document)->as($organiser)->expiringAt(CarbonImmutable::now()->subHour())->ask();

        $this->artisan('appointments:expire-approvals')->assertSuccessful();

        expect(Approval::query()->where('approvable_type', 'appointment')->sole()->status)
            ->toBe(ApprovalStatus::Expired)
            ->and(Approval::query()->where('approvable_type', $document->getMorphClass())->sole()->status)
            ->toBe(ApprovalStatus::Pending);
    } finally {
        Relation::morphMap([], false);
    }
});
