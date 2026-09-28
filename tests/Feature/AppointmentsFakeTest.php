<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Frequency;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Testing\AppointmentsFake;
use RoundlyConsulting\Appointments\Tests\Models\User;

beforeEach(function (): void {
    $this->fake = Appointments::fake();
});

it('installs itself behind the facade and for injected managers', function (): void {
    expect($this->fake)->toBeInstanceOf(AppointmentsFake::class)
        ->and(app(AppointmentManager::class))->toBe($this->fake)
        ->and(Appointments::getFacadeRoot())->toBe($this->fake);
});

it('still performs every operation', function (): void {
    $appointment = Appointments::schedule('Real')->startingAt('2026-07-01 09:00')->create();

    Appointments::for($appointment)->confirm();

    expect($appointment->fresh()->status)->toBe(Status::Confirmed);
});

describe('scheduled', function (): void {
    it('records the builder, DTO and recurring paths', function (): void {
        Appointments::schedule('Built')->startingAt('2026-07-01 09:00')->create();
        app(AppointmentManager::class)->create(new AppointmentData('Injected', CarbonImmutable::parse('2026-07-01 11:00')));
        Appointments::schedule('Series')
            ->startingAt('2026-07-01 13:00')
            ->recurring(new RecurrenceData(Frequency::Daily, count: 2))
            ->createRecurring();

        $this->fake->assertScheduled();
        $this->fake->assertScheduled(fn (Appointment $appointment): bool => $appointment->name === 'Built');
        $this->fake->assertScheduled(fn (Appointment $appointment): bool => $appointment->name === 'Injected');
        Appointments::assertScheduled(fn (Appointment $appointment): bool => $appointment->name === 'Series'
            && $appointment->starts_at->format('Y-m-d') === '2026-07-02');

        expect(fn () => $this->fake->assertScheduled(fn (Appointment $appointment): bool => $appointment->name === 'Other'))
            ->toThrow(ExpectationFailedException::class, 'matching the callback')
            ->and(fn () => $this->fake->assertNothingScheduled())
            ->toThrow(ExpectationFailedException::class, 'but there were 4');
    });

    it('records one call per appointment of a DTO series', function (): void {
        Appointments::createRecurring(
            new AppointmentData('Weekly', CarbonImmutable::parse('2026-07-01 09:00')),
            new RecurrenceData(Frequency::Weekly, count: 3),
        );
        Appointments::createRecurring(new AppointmentData('Single', CarbonImmutable::parse('2026-07-01 09:00')));

        expect(fn () => $this->fake->assertNothingScheduled())->toThrow(ExpectationFailedException::class, 'but there were 4');
    });

    it('passes assertNothingScheduled and fails assertScheduled when nothing ran', function (): void {
        $this->fake->assertNothingScheduled();

        expect(fn () => $this->fake->assertScheduled())
            ->toThrow(ExpectationFailedException::class, 'Expected an appointment to be scheduled, but there was none.');
    });
});

describe('rescheduled', function (): void {
    it('records the appointment and its previous start', function (): void {
        $appointment = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-07-01 09:00')]);

        Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-03 09:00'));

        $this->fake->assertRescheduled();
        $this->fake->assertRescheduled(fn (Appointment $moved, CarbonImmutable $previous): bool => $moved->is($appointment)
            && $previous->format('Y-m-d') === '2026-07-01');

        expect(fn () => $this->fake->assertRescheduled(fn (Appointment $moved, CarbonImmutable $previous): bool => $previous->format('Y-m-d') === '2026-07-03'))
            ->toThrow(ExpectationFailedException::class)
            ->and(fn () => $this->fake->assertNothingRescheduled())
            ->toThrow(ExpectationFailedException::class);
    });

    it('passes assertNothingRescheduled when nothing moved', function (): void {
        $this->fake->assertNothingRescheduled();

        expect(fn () => $this->fake->assertRescheduled())->toThrow(ExpectationFailedException::class);
    });
});

describe('transitioned', function (): void {
    it('records the handle and the model shortcut paths', function (): void {
        $viaHandle = Appointment::factory()->withStatus(Status::Pending)->create();
        $viaModel = Appointment::factory()->withStatus(Status::Pending)->create();

        Appointments::for($viaHandle)->confirm();
        $viaModel->cancel();

        $this->fake->assertTransitioned(fn (Appointment $appointment, Status $to, Status $from): bool => $appointment->is($viaHandle)
            && $to === Status::Confirmed && $from === Status::Pending);
        $this->fake->assertTransitioned(fn (Appointment $appointment, Status $to): bool => $appointment->is($viaModel)
            && $to === Status::Cancelled);

        expect(fn () => $this->fake->assertTransitioned(fn (Appointment $appointment, Status $to): bool => $to === Status::NoShow))
            ->toThrow(ExpectationFailedException::class)
            ->and(fn () => $this->fake->assertNothingTransitioned())
            ->toThrow(ExpectationFailedException::class, 'but there were 2');
    });

    it('does not record a refused transition', function (): void {
        $appointment = Appointment::factory()->withStatus(Status::Completed)->create();

        try {
            $appointment->transitionTo(Status::Pending);
        } catch (Throwable) {
        }

        $this->fake->assertNothingTransitioned();

        expect(fn () => $this->fake->assertTransitioned())->toThrow(ExpectationFailedException::class);
    });
});

describe('participants', function (): void {
    it('records an added participant', function (): void {
        $appointment = Appointment::factory()->create();
        $user = User::create();

        Appointments::for($appointment)->participants()->add($user, ParticipantRole::Optional);

        $this->fake->assertParticipantAdded();
        $this->fake->assertParticipantAdded(fn (Appointment $to, Model $participant, Participant $row): bool => $to->is($appointment)
            && $participant->is($user)
            && $row->role === ParticipantRole::Optional);

        expect(fn () => $this->fake->assertParticipantAdded(fn (Appointment $to, Model $participant): bool => $participant->is(User::create())))
            ->toThrow(ExpectationFailedException::class)
            ->and(fn () => $this->fake->assertNoParticipantAdded())
            ->toThrow(ExpectationFailedException::class);
    });

    it('does not count participants listed on a new appointment as added', function (): void {
        Appointments::schedule('With guest')->startingAt('2026-07-01 09:00')->withParticipant(User::create())->create();

        $this->fake->assertNoParticipantAdded();

        expect(fn () => $this->fake->assertParticipantAdded())->toThrow(ExpectationFailedException::class);
    });

    it('records a removed participant as its model, whether removed by model or by row', function (): void {
        $appointment = Appointment::factory()->create();
        $byModel = User::create();
        $byRow = User::create();
        Appointments::for($appointment)->participants()->add($byModel);
        $row = Appointments::for($appointment)->participants()->add($byRow);

        Appointments::for($appointment)->participants()->remove($byModel);
        Appointments::for($appointment)->participants()->remove($row);

        $this->fake->assertParticipantRemoved(fn (Appointment $from, Model $participant): bool => $participant->is($byModel));
        $this->fake->assertParticipantRemoved(fn (Appointment $from, Model $participant): bool => $from->is($appointment)
            && $participant->is($byRow));

        expect(fn () => $this->fake->assertParticipantRemoved(fn (Appointment $from, Model $participant): bool => $participant instanceof Participant))
            ->toThrow(ExpectationFailedException::class)
            ->and(fn () => $this->fake->assertNoParticipantRemoved())
            ->toThrow(ExpectationFailedException::class);
    });

    it('passes assertNoParticipantRemoved when nobody left', function (): void {
        $this->fake->assertNoParticipantRemoved();

        expect(fn () => $this->fake->assertParticipantRemoved())->toThrow(ExpectationFailedException::class);
    });
});
