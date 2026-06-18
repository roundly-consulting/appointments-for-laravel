<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

/**
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

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
