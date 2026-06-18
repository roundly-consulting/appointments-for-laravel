<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Database\Factories\AppointmentFactory;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\AppointmentUpdated;

/**
 * @property string $name
 * @property ?string $description
 * @property Status $status
 * @property ?Collection<array-key, mixed> $meta
 * @property CarbonInterface $appointment_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 */
final class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => AppointmentCreated::class,
        'updated' => AppointmentUpdated::class,
    ];

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        /** @var class-string<Participant> $model */
        $model = config('appointments.participant', Participant::class);

        return $this->hasMany($model);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'meta' => 'collection',
            'appointment_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AppointmentFactory::new();
    }
}
