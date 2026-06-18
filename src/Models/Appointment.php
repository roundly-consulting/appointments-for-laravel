<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Actions\TransitionAppointmentAction;
use RoundlyConsulting\Appointments\Database\Factories\AppointmentFactory;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\AppointmentUpdated;
use RoundlyConsulting\Appointments\Models\Concerns\HasAppointmentScopes;
use RoundlyConsulting\Appointments\Support\Ics\IcsGenerator;

/**
 * @property int $id
 * @property string $name
 * @property ?string $description
 * @property Status $status
 * @property ?Collection<array-key, mixed> $meta
 * @property ?string $timezone
 * @property CarbonInterface $starts_at
 * @property ?CarbonInterface $ends_at
 * @property ?int $duration_minutes
 * @property ?string $recurrence_group
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 */
final class Appointment extends Model
{
    use HasAppointmentScopes;

    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => AppointmentCreated::class,
        'updated' => AppointmentUpdated::class,
    ];

    protected static function booted(): void
    {
        self::saving(static function (Appointment $appointment): void {
            $appointment->syncEndsAt();
        });
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        /** @var class-string<Participant> $model */
        $model = config('appointments.participant', Participant::class);

        return $this->hasMany($model);
    }

    public function duration(): CarbonInterval
    {
        return CarbonInterval::minutes($this->durationInMinutes());
    }

    public function durationInMinutes(): int
    {
        if ($this->duration_minutes !== null) {
            return $this->duration_minutes;
        }

        if ($this->ends_at !== null) {
            return (int) $this->starts_at->diffInMinutes($this->ends_at);
        }

        /** @var int $default */
        $default = config('appointments.default_duration_minutes', 60);

        return $default;
    }

    public function resolveTimezone(): string
    {
        if ($this->timezone !== null && $this->timezone !== '') {
            return $this->timezone;
        }

        /** @var string|null $configured */
        $configured = config('appointments.timezone');

        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        /** @var string $appTimezone */
        $appTimezone = config('app.timezone', 'UTC');

        return $appTimezone;
    }

    public function startsAtLocal(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->starts_at)->setTimezone($this->resolveTimezone());
    }

    public function endsAtLocal(): ?CarbonImmutable
    {
        if ($this->ends_at === null) {
            return null;
        }

        return CarbonImmutable::instance($this->ends_at)->setTimezone($this->resolveTimezone());
    }

    public function confirm(): self
    {
        return $this->transitionTo(Status::Confirmed);
    }

    public function cancel(): self
    {
        return $this->transitionTo(Status::Cancelled);
    }

    public function complete(): self
    {
        return $this->transitionTo(Status::Completed);
    }

    public function decline(): self
    {
        return $this->transitionTo(Status::Declined);
    }

    public function markNoShow(): self
    {
        return $this->transitionTo(Status::NoShow);
    }

    public function transitionTo(Status $status): self
    {
        return app(TransitionAppointmentAction::class)->execute($this, $status);
    }

    public function toIcs(): string
    {
        return app(IcsGenerator::class)->forAppointment($this);
    }

    private function syncEndsAt(): void
    {
        if ($this->ends_at !== null) {
            if ($this->duration_minutes === null) {
                $this->duration_minutes = (int) $this->starts_at->diffInMinutes($this->ends_at);
            }

            return;
        }

        $minutes = $this->duration_minutes ?? (int) config('appointments.default_duration_minutes', 60);

        $this->duration_minutes = $minutes;
        $this->ends_at = CarbonImmutable::instance($this->starts_at)->addMinutes($minutes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'meta' => 'collection',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
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
