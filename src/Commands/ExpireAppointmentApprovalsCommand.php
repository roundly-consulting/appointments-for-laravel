<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Approvals\Facades\Approvals;

/**
 * Run the approvals engine's expiry. It is app-wide — approvals-for-laravel cannot yet scope it
 * to one subject type — so it lapses every expired approval decision and request, the
 * appointments' among them; an appointment whose request resolves as a result flips to Cancelled
 * through the SyncAppointmentStatusFromApproval listener.
 */
final class ExpireAppointmentApprovalsCommand extends Command
{
    protected $signature = 'appointments:expire-approvals';

    protected $description = 'Lapse every expired approval decision and request (app-wide) and cancel the affected appointments';

    public function handle(): int
    {
        $lapsed = Approvals::expire();

        $this->info("Lapsed {$lapsed} expired approval decision(s).");

        return self::SUCCESS;
    }
}
