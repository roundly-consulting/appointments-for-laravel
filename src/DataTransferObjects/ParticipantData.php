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
}
