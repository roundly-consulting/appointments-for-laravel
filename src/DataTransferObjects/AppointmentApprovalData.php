<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\DataTransferObjects\StageDefinition;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;

/**
 * Describes the approval workflow an appointment should open when it is created.
 *
 * A named workflow preset wins first, then an explicit staged pipeline, then the
 * flat approver set with a rule + quorum.
 */
final readonly class AppointmentApprovalData
{
    /**
     * @param  list<Model>  $approvers
     * @param  list<StageDefinition>  $stages
     * @param  list<list<Model>>  $stageApprovers
     */
    public function __construct(
        public array $approvers = [],
        public ApprovalRule $rule = ApprovalRule::Unanimous,
        public ?int $quorum = null,
        public array $stages = [],
        public ?string $workflow = null,
        public array $stageApprovers = [],
        public bool $rejectOnStageRejection = true,
    ) {}
}
