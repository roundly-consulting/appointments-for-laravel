<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentApprovalData;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Appointments\Support\DefaultTimezone;
use RoundlyConsulting\Appointments\Support\SchedulingLock;
use RoundlyConsulting\Approvals\ApprovalsManager;

final readonly class CreateAppointmentAction
{
    public function __construct(
        private ConflictDetector $conflicts,
        private ApprovalsManager $approvals,
        private SchedulingLock $lock,
    ) {}

    /**
     * The appointment, its participants, contacts and approval request are written in one
     * transaction, and the package events fire only once it commits — so a refused or failed
     * booking (a conflict, an invalid contact, an unknown approval workflow) leaves no row and
     * fires no event.
     *
     * @throws DuplicateParticipantException when the same model is listed twice
     * @throws SchedulingConflictException when conflicts are prevented and a participant is booked
     */
    public function execute(AppointmentData $data): Appointment
    {
        // The columns hold UTC; the appointment's timezone (stored, so a later config change
        // does not re-time it) drives local display.
        $startsAt = $data->startsAt->utc();
        $endsAt = $this->endsAt($startsAt, $data->durationMinutes);

        $this->guardAgainstDuplicates($data);

        $participants = array_map(static fn (ParticipantData $participant) => $participant->participant, $data->participants);
        $prevent = $this->lock->preventing($data->preventConflicts);

        return $this->lock->transaction($participants, function () use ($data, $startsAt, $endsAt, $participants, $prevent): Appointment {
            if ($prevent) {
                $this->lock->participants($participants);
                $this->guardAgainstConflicts($data, $startsAt, $endsAt);
            }

            $model = AppointmentModel::class();

            $appointment = $model::query()->create([
                'name' => $data->name,
                'description' => $data->description,
                'status' => $data->status,
                'meta' => $data->meta,
                'timezone' => $data->timezone ?? DefaultTimezone::resolve(),
                'location' => $data->location,
                'coordinates' => $data->coordinates,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $data->durationMinutes,
                'recurrence_group' => $data->recurrenceGroup,
            ]);

            foreach ($data->participants as $participant) {
                $appointment->participants()->create($participant->toAttributes());
            }

            foreach ($data->contacts as $contact) {
                $appointment->addContact($contact);
            }

            if ($data->approval !== null) {
                $this->openApprovalRequest($appointment, $data->approval);
            }

            return $appointment->load('participants');
        });
    }

    /**
     * Open an approvals-engine request for the appointment. A named workflow preset
     * wins first, then an explicit staged pipeline, then the flat approver set.
     */
    private function openApprovalRequest(Appointment $appointment, AppointmentApprovalData $approval): void
    {
        if ($approval->workflow !== null) {
            $approvers = $approval->stageApprovers !== [] ? $approval->stageApprovers : $approval->approvers;

            $this->approvals->request($appointment)->workflow($approval->workflow)->open($approvers);

            return;
        }

        if ($approval->stages !== []) {
            $this->approvals->request($appointment)
                ->stages($approval->stages)
                ->continueOnRejection(! $approval->rejectOnStageRejection)
                ->open();

            return;
        }

        if ($approval->approvers === []) {
            return;
        }

        $this->approvals->request($appointment)
            ->from($approval->approvers)
            ->rule($approval->rule, $approval->quorum)
            ->open();
    }

    private function endsAt(CarbonImmutable $startsAt, ?int $durationMinutes): CarbonImmutable
    {
        $minutes = $durationMinutes ?? (int) config('appointments.default_duration_minutes', 60);

        return $startsAt->addMinutes($minutes);
    }

    private function guardAgainstDuplicates(AppointmentData $data): void
    {
        foreach ($data->participants as $index => $participant) {
            foreach (array_slice($data->participants, $index + 1) as $other) {
                if ($participant->isSameParticipantAs($other)) {
                    throw DuplicateParticipantException::make($participant->participant);
                }
            }
        }
    }

    private function guardAgainstConflicts(AppointmentData $data, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        foreach ($data->participants as $participant) {
            $conflicts = $this->conflicts->forParticipant($participant->participant, $startsAt, $endsAt);

            if ($conflicts->isNotEmpty()) {
                throw SchedulingConflictException::make($conflicts);
            }
        }
    }
}
