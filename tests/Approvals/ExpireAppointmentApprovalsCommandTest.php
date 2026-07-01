<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\Approval;

it('lapses expired appointment-approval decisions', function (): void {
    $organiser = User::create();

    $appointment = Appointments::for('Expiring booking')
        ->startingAt('2026-07-01 09:00')
        ->requireApprovalFrom($organiser)
        ->create();

    // A pending decision that expired an hour ago.
    Approvals::for($appointment)->as($organiser)->expiringAt(CarbonImmutable::now()->subHour())->request();

    $this->artisan('appointments:expire-approvals')
        ->expectsOutputToContain('Lapsed 1 expired approval decision(s).')
        ->assertSuccessful();

    expect(Approval::query()->where('status', ApprovalStatus::Expired)->count())->toBe(1);
});
