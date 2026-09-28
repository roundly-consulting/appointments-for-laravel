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
use RoundlyConsulting\Appointments\AppointmentManager;
use RoundlyConsulting\Appointments\Database\Factories\AppointmentFactory;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Events\AppointmentCreated;
use RoundlyConsulting\Appointments\Events\AppointmentUpdated;
use RoundlyConsulting\Appointments\Exceptions\CannotReviewAppointmentException;
use RoundlyConsulting\Appointments\Models\Concerns\HasAppointmentScopes;
use RoundlyConsulting\Appointments\Reviews\VerifiedAttendanceResolver;
use RoundlyConsulting\Appointments\Support\ParticipantModel;
use RoundlyConsulting\Approvals\Traits\RequiresApproval;
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Geolocation\Casts\CoordinatesCast;
use RoundlyConsulting\Geolocation\Concerns\HasLocation;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Reviews\Concerns\HasReviews;
use RoundlyConsulting\Reviews\Support\PendingReview;

/**
 * @property int $id
 * @property string $name
 * @property ?string $description
 * @property Status $status
 * @property ?Collection<array-key, mixed> $meta
 * @property ?string $timezone
 * @property ?string $location
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?Coordinates $coordinates
 * @property CarbonInterface $starts_at
 * @property ?CarbonInterface $ends_at
 * @property ?int $duration_minutes
 * @property ?string $recurrence_group
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 *
 * Deliberately not final: `appointments.model` invites host apps to swap in
 * their own model extending this one.
 */
class Appointment extends Model
{
    use HasAppointmentScopes;
    use HasContacts;

    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use HasLocation;
    use HasReviews;
    use RequiresApproval;
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
        // The foreign key is named explicitly: a host model configured on
        // `appointments.model` would otherwise derive it from its own class name.
        return $this->hasMany(ParticipantModel::class(), 'appointment_id');
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

    /*
     * Lifecycle and export shortcuts. Each goes through the manager — never an action — so a
     * host override and `Appointments::fake()` see the call.
     */

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
        app(AppointmentManager::class)->for($this)->transition($status);

        return $this;
    }

    public function toIcs(): string
    {
        return app(AppointmentManager::class)->for($this)->ics();
    }

    /**
     * Great-circle distance in kilometres from this appointment's venue to a point,
     * or null when the appointment has no coordinates.
     */
    public function distanceFrom(Coordinates $point): ?float
    {
        $coordinates = $this->coordinates;

        if ($coordinates === null) {
            return null;
        }

        return $coordinates->distanceTo($point) / 1000;
    }

    /**
     * Open a review of this appointment by the given author. The review is stamped
     * "verified" when the author's attendance checks out via the configured
     * VerifiedAttendanceResolver.
     */
    public function review(Model $author): PendingReview
    {
        $verified = app(VerifiedAttendanceResolver::class)->verified($author, $this);

        if (! $verified && (bool) config('appointments.reviews.require_verified_attendance', false)) {
            throw CannotReviewAppointmentException::unverifiedAttendance($this);
        }

        return $this->addReview($author)->verified($verified);
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
            'coordinates' => CoordinatesCast::class,
            'latitude' => 'float',
            'longitude' => 'float',
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
