<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Appointments\Events\ParticipantCreated;
use RoundlyConsulting\Appointments\Events\ParticipantDeleted;
use RoundlyConsulting\Appointments\Events\ParticipantUpdated;

/**
 * @property int $appointment_id
 * @property string $participant_type
 * @property int $participant_id
 * @property ?Collection<array-key, mixed> $meta
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 */
final class Participant extends Model
{
    use SoftDeletes;

    protected $table = 'appointment_participants';

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => ParticipantCreated::class,
        'updated' => ParticipantUpdated::class,
        'deleted' => ParticipantDeleted::class,
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function appointment(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('appointments.model', Appointment::class);

        return $this->belongsTo($model);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
        ];
    }
}
