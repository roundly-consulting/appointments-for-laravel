<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Testing;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\DataTransferObjects\RecurrenceData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

/**
 * The recording, still-performing stand-in {@see Appointments::fake()} swaps in.
 *
 * Every operation runs against the database as usual — so availability checks, `participants()`
 * reads, the `HasAppointments` trait and the package events behave normally — while each
 * mutation is recorded, from wherever it came: the facade, an injected {@see AppointmentManager},
 * the `schedule()` builder, a `for()` handle or an `Appointment` model method (`confirm()`,
 * `cancel()`, `transitionTo()`, …).
 *
 * Participants listed on a new appointment are part of its scheduling, not "added": only
 * `for($appointment)->participants()->add()` records a participant as added.
 *
 * This class lives in src/ so host apps can use it; it depends on PHPUnit's Assert, which is
 * always present in a Laravel app's dev dependencies.
 */
final class AppointmentsFake extends AppointmentManager
{
    /** @var list<Appointment> */
    private array $scheduled = [];

    /** @var list<array{appointment: Appointment, previous: CarbonImmutable}> */
    private array $rescheduled = [];

    /** @var list<array{appointment: Appointment, from: Status, to: Status}> */
    private array $transitioned = [];

    /** @var list<array{appointment: Appointment, participant: Model, row: Participant}> */
    private array $added = [];

    /** @var list<array{appointment: Appointment, participant: Model}> */
    private array $removed = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function create(AppointmentData $data): Appointment
    {
        $appointment = parent::create($data);

        $this->scheduled[] = $appointment;

        return $appointment;
    }

    public function createRecurring(AppointmentData $data, ?RecurrenceData $rule = null): Collection
    {
        $series = parent::createRecurring($data, $rule);

        foreach ($series as $appointment) {
            $this->scheduled[] = $appointment;
        }

        return $series;
    }

    public function rescheduleFor(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        ?int $durationMinutes = null,
        bool $preventConflicts = false,
    ): Appointment {
        $previous = CarbonImmutable::instance($appointment->starts_at);

        $appointment = parent::rescheduleFor($appointment, $startsAt, $durationMinutes, $preventConflicts);

        $this->rescheduled[] = ['appointment' => $appointment, 'previous' => $previous];

        return $appointment;
    }

    public function transitionFor(Appointment $appointment, Status $to): Appointment
    {
        $from = $appointment->status;

        $appointment = parent::transitionFor($appointment, $to);

        // Asking for the status it already has is a no-op — no write, no event — so not a transition.
        if ($from !== $to) {
            $this->transitioned[] = ['appointment' => $appointment, 'from' => $from, 'to' => $to];
        }

        return $appointment;
    }

    public function addParticipantFor(Appointment $appointment, ParticipantData $data, bool $preventConflicts = false): Participant
    {
        $row = parent::addParticipantFor($appointment, $data, $preventConflicts);

        $this->added[] = ['appointment' => $appointment, 'participant' => $data->participant, 'row' => $row];

        return $row;
    }

    public function removeParticipantFor(Appointment $appointment, Model $participant): void
    {
        // A participant row is recorded as the model it stands for, so one callback serves both.
        $related = $participant instanceof Participant ? $participant->participant : null;

        parent::removeParticipantFor($appointment, $participant);

        $this->removed[] = ['appointment' => $appointment, 'participant' => $related ?? $participant];
    }

    /**
     * @param  (Closure(Appointment): bool)|null  $callback
     */
    public function assertScheduled(?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->scheduled,
            static fn (Appointment $appointment): bool => $callback === null || $callback($appointment),
        );

        PHPUnit::assertNotEmpty($matching, $this->failure('an appointment to be scheduled', $callback));
    }

    public function assertNothingScheduled(): void
    {
        $this->assertNone($this->scheduled, 'no appointment to be scheduled');
    }

    /**
     * @param  (Closure(Appointment, CarbonImmutable): bool)|null  $callback  receives the
     *                                                                        appointment and its previous start
     */
    public function assertRescheduled(?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->rescheduled,
            static fn (array $call): bool => $callback === null || $callback($call['appointment'], $call['previous']),
        );

        PHPUnit::assertNotEmpty($matching, $this->failure('an appointment to be rescheduled', $callback));
    }

    public function assertNothingRescheduled(): void
    {
        $this->assertNone($this->rescheduled, 'no appointment to be rescheduled');
    }

    /**
     * @param  (Closure(Appointment, Status, Status): bool)|null  $callback  receives the appointment,
     *                                                                       the target status and the previous one
     */
    public function assertTransitioned(?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->transitioned,
            static fn (array $call): bool => $callback === null || $callback($call['appointment'], $call['to'], $call['from']),
        );

        PHPUnit::assertNotEmpty($matching, $this->failure('an appointment status transition', $callback));
    }

    public function assertNothingTransitioned(): void
    {
        $this->assertNone($this->transitioned, 'no appointment status transition');
    }

    /**
     * @param  (Closure(Appointment, Model, Participant): bool)|null  $callback  receives the
     *                                                                           appointment, the participating model and its new row
     */
    public function assertParticipantAdded(?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->added,
            static fn (array $call): bool => $callback === null || $callback($call['appointment'], $call['participant'], $call['row']),
        );

        PHPUnit::assertNotEmpty($matching, $this->failure('a participant to be added', $callback));
    }

    public function assertNoParticipantAdded(): void
    {
        $this->assertNone($this->added, 'no participant to be added');
    }

    /**
     * @param  (Closure(Appointment, Model): bool)|null  $callback  receives the appointment and the
     *                                                              participating model
     */
    public function assertParticipantRemoved(?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->removed,
            static fn (array $call): bool => $callback === null || $callback($call['appointment'], $call['participant']),
        );

        PHPUnit::assertNotEmpty($matching, $this->failure('a participant to be removed', $callback));
    }

    public function assertNoParticipantRemoved(): void
    {
        $this->assertNone($this->removed, 'no participant to be removed');
    }

    private function failure(string $expectation, ?Closure $callback): string
    {
        return $callback === null
            ? "Expected {$expectation}, but there was none."
            : "Expected {$expectation} matching the callback, but none matched.";
    }

    /**
     * @param  list<mixed>  $calls
     */
    private function assertNone(array $calls, string $expectation): void
    {
        $count = count($calls);

        PHPUnit::assertSame(0, $count, "Expected {$expectation}, but there were {$count}.");
    }
}
