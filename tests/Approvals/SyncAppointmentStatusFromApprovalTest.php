<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;

function appointmentApprovalRequest(Appointment $appointment, ApprovalStatus $status): ApprovalRequest
{
    $request = new ApprovalRequest;
    $request->subject_id = $appointment->getKey();
    $request->subject_type = $appointment->getMorphClass();
    $request->rule = ApprovalRule::Unanimous;
    $request->required_approvers = 1;
    $request->status = $status;
    $request->save();

    return $request;
}

function pendingAppointment(): Appointment
{
    return Appointments::schedule('Booking')->startingAt('2026-07-01 09:00')->create();
}

it('cancels the appointment when its approval expires', function (): void {
    $appointment = pendingAppointment();

    event(new ApprovalRequestResolved(appointmentApprovalRequest($appointment, ApprovalStatus::Expired)));

    expect($appointment->fresh()?->status)->toBe(Status::Cancelled);
});

it('cancels the appointment when its approval is cancelled', function (): void {
    $appointment = pendingAppointment();

    event(new ApprovalRequestResolved(appointmentApprovalRequest($appointment, ApprovalStatus::Cancelled)));

    expect($appointment->fresh()?->status)->toBe(Status::Cancelled);
});

it('ignores a pending resolution', function (): void {
    $appointment = pendingAppointment();

    event(new ApprovalRequestResolved(appointmentApprovalRequest($appointment, ApprovalStatus::Pending)));

    expect($appointment->fresh()?->status)->toBe(Status::Pending);
});

it('ignores a resolution whose subject is not an appointment', function (): void {
    $user = User::create();

    $request = new ApprovalRequest;
    $request->subject_id = $user->getKey();
    $request->subject_type = $user->getMorphClass();
    $request->rule = ApprovalRule::Unanimous;
    $request->required_approvers = 1;
    $request->status = ApprovalStatus::Approved;
    $request->save();

    // No exception: the listener returns early for a non-Appointment subject.
    event(new ApprovalRequestResolved($request));
})->throwsNoExceptions();
