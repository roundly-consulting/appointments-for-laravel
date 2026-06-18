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
            'name' => fake()->name(),
            'description' => fake()->text(),
            'status' => fake()->randomElement([Status::New, Status::Accepted, Status::Rejected]),
            'appointment_at' => now(),
        ];
    }
}
