<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Enums\ParticipantRole;

final readonly class ParticipantData
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public Model $participant,
        public ?ParticipantRole $role = null,
        public ?array $meta = null,
    ) {}

    /**
     * Whether both describe the same participating model (type and key).
     */
    public function isSameParticipantAs(self $other): bool
    {
        return $this->participant->getMorphClass() === $other->participant->getMorphClass()
            && (string) $this->participant->getKey() === (string) $other->participant->getKey();
    }

    /**
     * The participant row's attributes (the appointment key is set by the relation).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'participant_type' => $this->participant->getMorphClass(),
            'participant_id' => $this->participant->getKey(),
            'role' => $this->role,
            'meta' => $this->meta,
        ];
    }
}
