<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Enums;

use RoundlyConsulting\Enums\Helpers;

enum Status: string
{
    use Helpers;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Declined = 'declined';
    case NoShow = 'no_show';

    public static function default(): self
    {
        return self::Pending;
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Confirmed => 'blue',
            self::Cancelled => 'gray',
            self::Completed => 'green',
            self::Declined => 'red',
            self::NoShow => 'orange',
        };
    }

    /**
     * The statuses this status is allowed to transition to.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Declined, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled, self::NoShow],
            self::Cancelled, self::Completed, self::Declined, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), strict: true);
    }

    public function isFinal(): bool
    {
        return $this->transitions() === [];
    }
}
