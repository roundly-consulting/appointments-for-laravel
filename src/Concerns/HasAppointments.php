<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Models\Participant;
use RoundlyConsulting\Appointments\Support\AppointmentModel;
use RoundlyConsulting\Appointments\Support\ParticipantModel;

/**
 * Host models (User, Contact, Room, …) use this trait to expose their
 * appointments through the polymorphic participants table.
 *
 * @phpstan-require-extends Model
 */
trait HasAppointments
{
    /**
     * The participant rows linking this model to its appointments.
     *
     * @return MorphMany<Participant, $this>
     */
    public function appointmentParticipations(): MorphMany
    {
        return $this->morphMany(ParticipantModel::class(), 'participant');
    }

    /**
     * The appointments this model takes part in.
     *
     * @return Builder<Appointment>
     */
    public function appointments(): Builder
    {
        $model = AppointmentModel::class();

        return $model::query()->forParticipant($this);
    }
}
