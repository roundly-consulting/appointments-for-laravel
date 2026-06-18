<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;

final class AppointmentsService
{
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
        /** @var Appointment $appointment */
        $appointment = $this->newAppointmentModel()->newModelQuery()->create([
            'name' => $name,
            'description' => $description,
            'meta' => $meta,
            'appointment_at' => $appointmentAt,
        ]);

        ($participants ?? collect())->each(fn (array|Model $participant) => $this->addParticipantToAppointment(
            appointment: $appointment,
            participant: is_array($participant) ? $participant[0] : $participant,
            meta: is_array($participant) && isset($participant[1]) ? collect($participant[1]) : null,
        ));

        return $appointment;
    }

    /**
     * @param  ?Collection<array-key, mixed>  $meta
     */
    public function addParticipantToAppointment(
        Appointment $appointment,
        Model $participant,
        ?Collection $meta = null,
    ): Participant {
        /** @var Participant $appointmentParticipant */
        $appointmentParticipant = $appointment->participants()->create([
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
            'meta' => $meta,
        ]);

        return $appointmentParticipant;
    }

    protected function newAppointmentModel(): Appointment
    {
        /** @var class-string<Appointment> $model */
        $model = config('appointments.model', Appointment::class);

        return new $model;
    }
}
