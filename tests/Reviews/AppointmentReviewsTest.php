<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Exceptions\CannotReviewAppointmentException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Reviews\NullVerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Tests\Models\User;

function completedAppointmentWith(User $attendee): Appointment
{
    $appointment = Appointments::schedule('Consultation')
        ->startingAt('2026-07-01 09:00')
        ->withParticipant($attendee)
        ->create();

    $appointment->confirm();
    $appointment->complete();

    return $appointment->refresh();
}

it('marks a review by a completed attendee as verified', function (): void {
    $attendee = User::create();
    $appointment = completedAppointmentWith($attendee);

    $review = $appointment->review($attendee)->rating(5)->content('Great')->create();

    expect($review->verified)->toBeTrue()
        ->and($review->author->is($attendee))->toBeTrue();
});

it('leaves a review by a non-participant unverified', function (): void {
    $attendee = User::create();
    $stranger = User::create();
    $appointment = completedAppointmentWith($attendee);

    $review = $appointment->review($stranger)->rating(2)->create();

    expect($review->verified)->toBeFalse();
});

it('leaves a review on a non-completed appointment unverified', function (): void {
    $attendee = User::create();
    $appointment = Appointments::schedule('Upcoming')
        ->startingAt('2026-07-01 09:00')
        ->withParticipant($attendee)
        ->create();

    expect($appointment->status)->toBe(Status::Pending);

    $review = $appointment->review($attendee)->rating(4)->create();

    expect($review->verified)->toBeFalse();
});

it('rejects an unverified review when attendance is required', function (): void {
    config()->set('appointments.reviews.require_verified_attendance', true);

    $attendee = User::create();
    $stranger = User::create();
    $appointment = completedAppointmentWith($attendee);

    expect(fn () => $appointment->review($stranger)->rating(1)->create())
        ->toThrow(CannotReviewAppointmentException::class);
});

it('summarises approved ratings only', function (): void {
    config()->set('reviews.auto_approve', true);

    $appointment = completedAppointmentWith(User::create());

    $appointment->review(User::create())->rating(5)->create();
    $appointment->review(User::create())->rating(3)->create();

    expect($appointment->averageRating())->toBe(4.0)
        ->and($appointment->approvedReviewsCount())->toBe(2)
        ->and($appointment->ratingSummary()->count)->toBe(2);
});

it('behaves normally with the null resolver', function (): void {
    config()->set('appointments.reviews.verified_attendance_resolver', NullVerifiedAttendanceResolver::class);
    app()->forgetInstance(VerifiedAttendanceResolver::class);
    app()->bind(
        VerifiedAttendanceResolver::class,
        NullVerifiedAttendanceResolver::class,
    );

    $attendee = User::create();
    $appointment = completedAppointmentWith($attendee);

    $review = $appointment->review($attendee)->rating(5)->create();

    expect($review->verified)->toBeFalse();
});
