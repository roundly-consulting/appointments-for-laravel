<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\AppointmentUpdated;
use RoundlyConsulting\Appointments\Events\ParticipantCreated;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Approvals\Exceptions\UnknownWorkflowException;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;

/*
 * A booking is written in one transaction and announced only once it commits: whatever fails
 * part-way — a contact, an approval workflow, a later occurrence of a series — leaves no row
 * behind and no event for a booking that does not exist.
 */

/**
 * Count every package event fired from now on.
 *
 * @return ArrayObject<string, int>
 */
function countBookingEvents(): ArrayObject
{
    /** @var ArrayObject<string, int> $fired */
    $fired = new ArrayObject([AppointmentCreated::class => 0, AppointmentUpdated::class => 0, ParticipantCreated::class => 0]);

    foreach (array_keys($fired->getArrayCopy()) as $event) {
        Event::listen($event, function () use ($fired, $event): void {
            $fired[$event]++;
        });
    }

    return $fired;
}

it('leaves no row and fires no event when a contact is refused', function (): void {
    $fired = countBookingEvents();

    expect(fn () => Appointments::schedule('Guest booking')
        ->startingAt('2026-07-01 09:00')
        ->withParticipant(User::create())
        ->withContactEmail('not-an-email')
        ->create())->toThrow(InvalidContactValue::class);

    expect(Appointment::query()->withTrashed()->count())->toBe(0)
        ->and(Participant::query()->withTrashed()->count())->toBe(0)
        ->and($fired->getArrayCopy())->toBe([AppointmentCreated::class => 0, AppointmentUpdated::class => 0, ParticipantCreated::class => 0]);
});

it('leaves no row and fires no event when the approval workflow is unknown', function (): void {
    $fired = countBookingEvents();

    expect(fn () => Appointments::schedule('Preset booking')
        ->startingAt('2026-07-01 09:00')
        ->withParticipant(User::create())
        ->approvalWorkflow('does-not-exist')
        ->create())->toThrow(UnknownWorkflowException::class);

    expect(Appointment::query()->withTrashed()->count())->toBe(0)
        ->and(Participant::query()->withTrashed()->count())->toBe(0)
        ->and($fired[AppointmentCreated::class])->toBe(0)
        ->and($fired[ParticipantCreated::class])->toBe(0);
});

it('announces a booking that commits, once', function (): void {
    $fired = countBookingEvents();

    Appointments::schedule('Booked')->startingAt('2026-07-01 09:00')->withParticipant(User::create())->create();

    expect($fired->getArrayCopy())->toBe([AppointmentCreated::class => 1, AppointmentUpdated::class => 0, ParticipantCreated::class => 1]);
});

it('fires no event for the occurrences of a series that rolls back', function (): void {
    $host = User::create();

    // Week 3 of the series is taken.
    Appointments::schedule('Blocker')->startingAt('2026-07-20 09:00')->lasting(60)->withParticipant($host)->create();

    $fired = countBookingEvents();

    expect(fn () => Appointments::schedule('Weekly')
        ->startingAt('2026-07-06 09:00')
        ->lasting(30)
        ->withParticipant($host)
        ->preventConflicts()
        ->recurring(new RecurrenceData(Frequency::Weekly, count: 4))
        ->createRecurring())->toThrow(SchedulingConflictException::class);

    expect(Appointment::query()->count())->toBe(1)
        ->and($fired->getArrayCopy())->toBe([AppointmentCreated::class => 0, AppointmentUpdated::class => 0, ParticipantCreated::class => 0]);
});

it('creates each occurrence already in its series, without a follow-up update', function (): void {
    $fired = countBookingEvents();
    $groups = [];
    Event::listen(AppointmentCreated::class, function (AppointmentCreated $event) use (&$groups): void {
        $groups[] = $event->appointment->recurrence_group;
    });

    $series = Appointments::schedule('Weekly')
        ->startingAt(CarbonImmutable::parse('2026-07-06 09:00'))
        ->recurring(new RecurrenceData(Frequency::Weekly, count: 3))
        ->createRecurring();

    expect($series)->toHaveCount(3)
        ->and($fired[AppointmentCreated::class])->toBe(3)
        ->and($fired[AppointmentUpdated::class])->toBe(0)
        ->and(array_unique($groups))->toHaveCount(1)
        ->and($groups[0])->toBe($series->first()->recurrence_group)
        ->and($groups[0])->not->toBeNull();
});
