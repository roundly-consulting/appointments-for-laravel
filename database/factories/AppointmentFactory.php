<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\AppointmentModel;

/**
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * Build the configured model, not the packaged one: a host that swaps
     * `appointments.model` gets its own class, with its casts,
     * events and observers.
     *
     * @return class-string<Appointment>
     */
    public function modelName(): string
    {
        return AppointmentModel::class();
    }

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->text(),
            'status' => Status::Pending,
            'timezone' => null,
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
        ];
    }

    public function withStatus(Status $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
