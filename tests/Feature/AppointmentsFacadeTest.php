<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Appointments\AppointmentHandle;
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\AppointmentParticipants;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\ParticipantCreated;
use RoundlyConsulting\Appointments\Events\ParticipantDeleted;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Appointments\Exceptions\ParticipantNotFoundException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Tests\Models\User;

/**
 * Every facade method and every handle / sub-accessor method, exercised through the facade.
 */
function bookFor(User $user, string $start, int $minutes = 60, Status $status = Status::Pending): Appointment
{
    return Appointments::schedule('Booked')
        ->startingAt($start)
        ->lasting($minutes)
        ->withStatus($status)
        ->withParticipant($user)
        ->create();
}

describe('schedule / create / createRecurring', function (): void {
    it('schedules through the fluent builder', function (): void {
        $appointment = Appointments::schedule('Consultation')->startingAt('2026-07-01 09:00')->lasting(30)->create();

        expect($appointment->exists)->toBeTrue()
            ->and($appointment->name)->toBe('Consultation')
            ->and($appointment->ends_at->format('Y-m-d H:i'))->toBe('2026-07-01 09:30');
    });

    it('creates from a DTO', function (): void {
        $appointment = Appointments::create(new AppointmentData(
            name: 'Sync',
            startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
            participants: [new ParticipantData(User::create(), ParticipantRole::Organiser)],
        ));

        expect($appointment->participants)->toHaveCount(1)
            ->and($appointment->participants->first()->role)->toBe(ParticipantRole::Organiser);
    });

    it('creates a recurring series from a DTO and a rule', function (): void {
        $series = Appointments::createRecurring(
            new AppointmentData(name: 'Physio', startsAt: CarbonImmutable::parse('2026-07-01 09:00')),
            new RecurrenceData(Frequency::Weekly, count: 3),
        );

        expect($series)->toHaveCount(3)
            ->and($series->pluck('recurrence_group')->unique())->toHaveCount(1)
            ->and($series->last()->starts_at->format('Y-m-d'))->toBe('2026-07-15');
    });

    it('falls back to the DTO rule, and to a single appointment without one', function (): void {
        $fromDto = Appointments::createRecurring(new AppointmentData(
            name: 'Daily',
            startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
            recurrence: new RecurrenceData(Frequency::Daily, count: 2),
        ));

        $single = Appointments::createRecurring(new AppointmentData(
            name: 'Once',
            startsAt: CarbonImmutable::parse('2026-07-01 09:00'),
        ));

        expect($fromDto)->toHaveCount(2)
            ->and($single)->toHaveCount(1)
            ->and($single->first()->recurrence_group)->toBeNull();
    });

    it('refuses the same participant twice before writing anything', function (): void {
        Event::fake([AppointmentCreated::class]);
        $user = User::create();

        expect(fn () => Appointments::schedule('Twice')
            ->startingAt('2026-07-01 09:00')
            ->withParticipant($user)
            ->withParticipant($user, ParticipantRole::Optional)
            ->create())->toThrow(DuplicateParticipantException::class, 'already takes part in this appointment');

        expect(Appointment::query()->count())->toBe(0);
        Event::assertNotDispatched(AppointmentCreated::class);
    });
});

describe('for($appointment)', function (): void {
    it('returns a handle scoped to the appointment', function (): void {
        $appointment = Appointment::factory()->create();

        expect(Appointments::for($appointment))->toBeInstanceOf(AppointmentHandle::class)
            ->and(Appointments::for($appointment)->participants())->toBeInstanceOf(AppointmentParticipants::class);
    });

    it('reschedules, keeping or changing the duration', function (): void {
        $appointment = Appointment::factory()->create([
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00'),
            'duration_minutes' => 60,
        ]);

        Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-02 10:00'));
        expect($appointment->fresh()->ends_at->format('Y-m-d H:i'))->toBe('2026-07-02 11:00');

        Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-03 10:00'), 15);
        expect($appointment->fresh()->ends_at->format('Y-m-d H:i'))->toBe('2026-07-03 10:15');
    });

    it('reschedules with conflict prevention', function (): void {
        $user = User::create();
        bookFor($user, '2026-07-02 10:00');
        $appointment = bookFor($user, '2026-07-01 09:00');

        expect(fn () => Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-02 10:30'), preventConflicts: true))
            ->toThrow(SchedulingConflictException::class);
    });

    it('transitions and takes the lifecycle shortcuts', function (string $shortcut, Status $from, Status $to): void {
        $appointment = Appointment::factory()->withStatus($from)->create();

        Appointments::for($appointment)->{$shortcut}();

        expect($appointment->fresh()->status)->toBe($to);
    })->with([
        'confirm' => ['confirm', Status::Pending, Status::Confirmed],
        'cancel' => ['cancel', Status::Pending, Status::Cancelled],
        'decline' => ['decline', Status::Pending, Status::Declined],
        'complete' => ['complete', Status::Confirmed, Status::Completed],
        'markNoShow' => ['markNoShow', Status::Confirmed, Status::NoShow],
    ]);

    it('transitions to an explicit status and refuses a forbidden one', function (): void {
        $appointment = Appointment::factory()->withStatus(Status::Pending)->create();

        Appointments::for($appointment)->transition(Status::Confirmed);

        expect($appointment->fresh()->status)->toBe(Status::Confirmed)
            ->and(fn () => Appointments::for($appointment)->transition(Status::Pending))
            ->toThrow(InvalidStatusTransitionException::class);
    });

    it('exports one appointment as a calendar', function (): void {
        $appointment = Appointment::factory()->create(['name' => 'Checkup']);

        $ics = Appointments::for($appointment)->ics();

        expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1)
            ->and($ics)->toContain('SUMMARY:Checkup')
            ->and($appointment->toIcs())->toBe($ics);
    });
});

describe('for($appointment)->participants()', function (): void {
    it('adds a participant with role and meta, then lists and finds it', function (): void {
        Event::fake([ParticipantCreated::class]);
        $appointment = Appointment::factory()->create();
        $user = User::create();

        $row = Appointments::for($appointment)->participants()->add($user, ParticipantRole::Attendee, meta: ['seat' => 4]);

        $all = Appointments::for($appointment)->participants()->all();

        expect($row)->toBeInstanceOf(Participant::class)
            ->and($row->role)->toBe(ParticipantRole::Attendee)
            ->and($row->meta->toArray())->toBe(['seat' => 4])
            ->and($all)->toHaveCount(1)
            ->and($all->first()->participant->is($user))->toBeTrue()
            ->and(Appointments::for($appointment)->participants()->has($user))->toBeTrue()
            ->and(Appointments::for($appointment)->participants()->has($row))->toBeTrue()
            ->and(Appointments::for($appointment)->participants()->has(User::create()))->toBeFalse();

        Event::assertDispatched(ParticipantCreated::class);
    });

    it('refuses a participant that already takes part', function (): void {
        $user = User::create();
        $appointment = bookFor($user, '2026-07-01 09:00');

        expect(fn () => Appointments::for($appointment)->participants()->add($user))
            ->toThrow(DuplicateParticipantException::class, 'already takes part in appointment');
    });

    it('refuses a double-booked participant when conflicts are prevented', function (): void {
        $user = User::create();
        bookFor($user, '2026-07-01 09:30');
        $appointment = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-01 09:00')]);

        expect(fn () => Appointments::for($appointment)->participants()->add($user, preventConflicts: true))
            ->toThrow(SchedulingConflictException::class);

        config()->set('appointments.prevent_conflicts', true);

        expect(fn () => Appointments::for($appointment)->participants()->add($user))
            ->toThrow(SchedulingConflictException::class)
            ->and(Appointments::for($appointment)->participants()->has($user))->toBeFalse();
    });

    it('adds a participant whose clashing booking was cancelled', function (): void {
        $user = User::create();
        bookFor($user, '2026-07-01 09:00', status: Status::Cancelled);
        $appointment = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-01 09:00')]);

        Appointments::for($appointment)->participants()->add($user, preventConflicts: true);

        expect(Appointments::for($appointment)->participants()->has($user))->toBeTrue();
    });

    it('removes a participant by model or by row', function (): void {
        Event::fake([ParticipantDeleted::class]);
        $appointment = Appointment::factory()->create();
        $first = User::create();
        $second = User::create();
        Appointments::for($appointment)->participants()->add($first);
        $row = Appointments::for($appointment)->participants()->add($second);

        Appointments::for($appointment)->participants()->remove($first);
        Appointments::for($appointment)->participants()->remove($row);

        expect(Appointments::for($appointment)->participants()->all())->toBeEmpty();
        Event::assertDispatchedTimes(ParticipantDeleted::class, 2);
    });

    it('lets a removed participant be added again', function (): void {
        $user = User::create();
        $appointment = bookFor($user, '2026-07-01 09:00');

        Appointments::for($appointment)->participants()->remove($user);
        Appointments::for($appointment)->participants()->add($user);

        expect(Appointments::for($appointment)->participants()->all())->toHaveCount(1);
    });

    it('refuses to remove a model that does not take part', function (): void {
        $appointment = Appointment::factory()->create();

        expect(fn () => Appointments::for($appointment)->participants()->remove(User::create()))
            ->toThrow(ParticipantNotFoundException::class, 'does not take part in appointment');
    });

    it('refuses a participant row of another appointment', function (): void {
        $user = User::create();
        $mine = Appointment::factory()->create();
        $theirs = bookFor($user, '2026-07-01 09:00');
        $foreignRow = $theirs->participants->first();

        expect(Appointments::for($mine)->participants()->has($foreignRow))->toBeFalse()
            ->and(fn () => Appointments::for($mine)->participants()->remove($foreignRow))
            ->toThrow(ParticipantNotFoundException::class);

        expect(Appointments::for($theirs)->participants()->has($user))->toBeTrue();
    });

    it('keeps a loaded participants relation current', function (): void {
        $appointment = bookFor(User::create(), '2026-07-01 09:00');
        $newcomer = User::create();

        Appointments::for($appointment)->participants()->add($newcomer);
        expect($appointment->participants)->toHaveCount(2);

        Appointments::for($appointment)->participants()->remove($newcomer);
        expect($appointment->participants)->toHaveCount(1);
    });

    it('guards a reschedule for a participant added after the relation was loaded', function (): void {
        $newcomer = User::create();
        bookFor($newcomer, '2026-07-02 10:00');
        $appointment = bookFor(User::create(), '2026-07-01 09:00');

        // Bypassing the handle leaves the loaded relation stale — the guard must not trust it.
        $appointment->participants()->create((new ParticipantData($newcomer))->toAttributes());

        expect(fn () => Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-02 10:00'), preventConflicts: true))
            ->toThrow(SchedulingConflictException::class);
    });
});

describe('availability, feeds and previews', function (): void {
    it('lists conflicts and answers availability', function (): void {
        $user = User::create();
        $booked = bookFor($user, '2026-07-01 09:00');
        $start = CarbonImmutable::parse('2026-07-01 09:30');
        $end = CarbonImmutable::parse('2026-07-01 10:30');

        expect(Appointments::conflicts($user, $start, $end)->modelKeys())->toBe([$booked->getKey()])
            ->and(Appointments::isAvailable($user, $start, $end))->toBeFalse()
            ->and(Appointments::conflicts($user, $start, $end, ignore: $booked))->toBeEmpty()
            ->and(Appointments::isAvailable($user, $start, $end, ignore: $booked))->toBeTrue()
            ->and(Appointments::isAvailable($user, $end, $end->addHour()))->toBeTrue();
    });

    it('renders many appointments as one feed', function (): void {
        $appointments = Appointment::factory()->count(3)->create();

        $ics = Appointments::ics($appointments);

        expect(substr_count($ics, 'BEGIN:VCALENDAR'))->toBe(1)
            ->and(substr_count($ics, 'BEGIN:VEVENT'))->toBe(3);
    });

    it('previews occurrences without writing', function (): void {
        $occurrences = Appointments::occurrences(
            CarbonImmutable::parse('2026-07-01 09:00'),
            new RecurrenceData(Frequency::Weekly, interval: 2, count: 3),
        );

        expect(array_map(static fn (CarbonImmutable $at): string => $at->format('Y-m-d'), $occurrences))
            ->toBe(['2026-07-01', '2026-07-15', '2026-07-29'])
            ->and(Appointment::query()->count())->toBe(0);
    });
});

it('serves the same API to an injected manager', function (): void {
    $manager = app(AppointmentManager::class);
    $user = User::create();

    $appointment = $manager->schedule('Injected')->startingAt('2026-07-01 09:00')->create();
    $manager->for($appointment)->participants()->add($user);
    $manager->for($appointment)->confirm();

    expect($manager)->toBe(Appointments::getFacadeRoot())
        ->and($appointment->fresh()->status)->toBe(Status::Confirmed)
        ->and($manager->isAvailable($user, $appointment->starts_at, $appointment->ends_at))->toBeFalse();
});
