<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Approvals\Facades\Approvals;

/**
 * Lapse expired appointment-approval decisions. Any approval request that resolves
 * as a result flips its appointment to Cancelled through the
 * SyncAppointmentStatusFromApproval listener.
 */
final class ExpireAppointmentApprovalsCommand extends Command
{
    protected $signature = 'appointments:expire-approvals';

    protected $description = 'Lapse expired appointment-approval decisions and cancel the affected appointments';

    public function handle(): int
    {
        $lapsed = Approvals::expire();

        $this->info("Lapsed {$lapsed} expired approval decision(s).");

        return self::SUCCESS;
    }
}
