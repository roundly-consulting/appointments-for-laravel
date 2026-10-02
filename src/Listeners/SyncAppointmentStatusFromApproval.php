<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentStatusChanged;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;

/**
 * Mirrors an appointment's approval-request resolution onto its own Status, so an
 * organiser sign-off (direct decision, quorum reached, a staged pipeline clearing,
 * or expiry) moves the appointment Pending → Confirmed/Declined/Cancelled and fires
 * the appointment event surface — keeping that surface unchanged regardless of how
 * the decision arrived.
 *
 * `appointments.approvals.enforce_transitions` off (the default) forces the mapped status between
 * live statuses (confirmed → declined included); a final status is never left either way.
 */
final class SyncAppointmentStatusFromApproval
{
    public function handle(ApprovalRequestResolved $event): void
    {
        $approvalRequest = $event->request;
        $subject = $approvalRequest->subject;

        if (! $subject instanceof Appointment) {
            return;
        }

        $target = $this->map($approvalRequest->status);
        $from = $subject->status;

        if ($target === null || $from === $target) {
            return;
        }

        // A final status (cancelled, declined, completed, no-show) is never left — not even when
        // not enforcing: an approver deciding a request that is still open must not bring a
        // cancelled booking back to life.
        if ($from->isFinal()) {
            return;
        }

        if ($this->enforcing() && ! $from->canTransitionTo($target)) {
            return;
        }

        $this->stampActor($subject, $approvalRequest);

        $subject->status = $target;
        $subject->save();

        event(new AppointmentStatusChanged($subject, $from, $target));
    }

    private function map(ApprovalStatus $status): ?Status
    {
        return match ($status) {
            ApprovalStatus::Approved => Status::Confirmed,
            ApprovalStatus::Rejected => Status::Declined,
            ApprovalStatus::Cancelled, ApprovalStatus::Expired => Status::Cancelled,
            ApprovalStatus::Pending => null,
        };
    }

    private function stampActor(Appointment $appointment, ApprovalRequest $request): void
    {
        $decision = $request->decisions()
            ->whereIn('status', [ApprovalStatus::Approved, ApprovalStatus::Rejected])
            ->latest('id')
            ->first();

        $actor = $decision?->actor;

        if (! $actor instanceof Model) {
            return;
        }

        $meta = $appointment->meta ?? collect();
        $meta->put('approval_decided_by', [
            'type' => $actor->getMorphClass(),
            'id' => $actor->getKey(),
        ]);

        $appointment->meta = $meta;
    }

    private function enforcing(): bool
    {
        return (bool) config('appointments.approvals.enforce_transitions', false);
    }
}
