<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\Actions\CreateAppointmentAction;
use RoundlyConsulting\Appointments\DataTransferObjects\AppointmentData;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

/**
 * @deprecated Use the Appointments facade / builder or the action classes
 *             (CreateAppointmentAction, AttachParticipantAction) directly. This
 *             shim is kept for backward compatibility and will be removed in a
 *             future major version.
 */
final class AppointmentsService
{
    public function __construct(
        private readonly CreateAppointmentAction $createAppointment,
        private readonly AttachParticipantAction $attachParticipant,
    ) {}

    /**
     * @param  ?Collection<array-key, mixed>  $meta
     * @param  ?Collection<array-key, array{0: Model, 1?: array<string, mixed>}|Model>  $participants
     */
    public function create(
        string $name,
        Carbon $appointmentAt,
        ?string $description = null,
        ?Collection $meta = null,
        ?Collection $participants = null,
    ): Appointment {
        $participantData = array_values(
            ($participants ?? collect())
                ->map(function (array|Model $participant): ParticipantData {
                    if (is_array($participant)) {
                        /** @var array<string, mixed>|null $participantMeta */
                        $participantMeta = $participant[1] ?? null;

                        return new ParticipantData($participant[0], meta: $participantMeta);
                    }

                    return new ParticipantData($participant);
                })
                ->all(),
        );

        /** @var array<string, mixed>|null $metaArray */
        $metaArray = $meta?->toArray();

        return $this->createAppointment->execute(new AppointmentData(
            name: $name,
            startsAt: CarbonImmutable::instance($appointmentAt),
            description: $description,
            meta: $metaArray,
            participants: $participantData,
        ));
    }

    /**
     * @param  ?Collection<array-key, mixed>  $meta
     */
    public function addParticipantToAppointment(
        Appointment $appointment,
        Model $participant,
        ?Collection $meta = null,
    ): Participant {
        /** @var array<string, mixed>|null $metaArray */
        $metaArray = $meta?->toArray();

        return $this->attachParticipant->execute(
            $appointment,
            new ParticipantData($participant, meta: $metaArray),
        );
    }
}
