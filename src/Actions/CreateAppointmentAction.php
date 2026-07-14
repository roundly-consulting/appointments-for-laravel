<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentApprovalData;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\Exceptions\SchedulingConflictException;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\ConflictDetector;
use RoundlyConsulting\Approvals\Facades\Approvals;

final class CreateAppointmentAction
{
    public function __construct(
        private readonly AttachParticipantAction $attachParticipant,
        private readonly ConflictDetector $conflicts,
    ) {}

    public function execute(AppointmentData $data): Appointment
    {
        // Persist instants in UTC so the stored wall-clock value is unambiguous;
        // the appointment's timezone column drives local display.
        $startsAt = $data->startsAt->utc();
        $endsAt = $this->endsAt($startsAt, $data->durationMinutes);

        $this->guardAgainstConflicts($data, $startsAt, $endsAt);

        $model = AppointmentModel::class();

        $appointment = $model::query()->create([
            'name' => $data->name,
            'description' => $data->description,
            'status' => $data->status,
            'meta' => $data->meta,
            'timezone' => $data->timezone,
            'location' => $data->location,
            'coordinates' => $data->coordinates,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $data->durationMinutes,
        ]);

        foreach ($data->participants as $participant) {
            $this->attachParticipant->execute($appointment, $participant);
        }

        foreach ($data->contacts as $contact) {
            $appointment->addContact($contact);
        }

        if ($data->approval !== null) {
            $this->openApprovalRequest($appointment, $data->approval);
        }

        return $appointment->load('participants');
    }

    /**
     * Open an approvals-engine request for the appointment. A named workflow preset
     * wins first, then an explicit staged pipeline, then the flat approver set.
     */
    private function openApprovalRequest(Appointment $appointment, AppointmentApprovalData $approval): void
    {
        if ($approval->workflow !== null) {
            $approvers = $approval->stageApprovers !== [] ? $approval->stageApprovers : $approval->approvers;

            Approvals::for($appointment)->workflow($approval->workflow)->request($approvers);

            return;
        }

        if ($approval->stages !== []) {
            $appointment->requestStagedApproval($approval->stages, $approval->rejectOnStageRejection);

            return;
        }

        if ($approval->approvers === []) {
            return;
        }

        $appointment->requestApproval($approval->approvers, $approval->rule, $approval->quorum);
    }

    private function endsAt(CarbonImmutable $startsAt, ?int $durationMinutes): CarbonImmutable
    {
        $minutes = $durationMinutes ?? (int) config('appointments.default_duration_minutes', 60);

        return $startsAt->addMinutes($minutes);
    }

    private function guardAgainstConflicts(AppointmentData $data, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $prevent = $data->preventConflicts || (bool) config('appointments.prevent_conflicts', false);

        if (! $prevent) {
            return;
        }

        foreach ($data->participants as $participant) {
            $conflicts = $this->conflicts->forParticipant($participant->participant, $startsAt, $endsAt);

            if ($conflicts->isNotEmpty()) {
                throw SchedulingConflictException::make($conflicts);
            }
        }
    }
}
