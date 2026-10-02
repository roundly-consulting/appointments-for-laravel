<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Approvals\Facades\Approvals;

/**
 * Run the approvals engine's expiry scoped to appointments: it passes the configured
 * appointment model's morph class (its morph-map alias when one is registered), so only
 * expired appointment approval decisions and requests lapse — other subjects' approvals are
 * left alone. An appointment whose request resolves as a result flips to Cancelled through
 * the SyncAppointmentStatusFromApproval listener.
 */
final class ExpireAppointmentApprovalsCommand extends Command
{
    protected $signature = 'appointments:expire-approvals';

    protected $description = 'Lapse expired appointment approval decisions and requests and cancel the affected appointments';

    public function handle(): int
    {
        $model = AppointmentModel::class();

        $lapsed = Approvals::expire(subjectType: (new $model)->getMorphClass());

        $this->info("Lapsed {$lapsed} expired approval decision(s).");

        return self::SUCCESS;
    }
}
