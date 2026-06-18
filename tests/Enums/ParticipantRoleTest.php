<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\ParticipantRole;

it('defaults to attendee', function (): void {
    expect(ParticipantRole::default())->toBe(ParticipantRole::Attendee);
});

it('resolves a translatable label', function (): void {
    expect(ParticipantRole::Organiser->label())->toBe('Organiser')
        ->and(ParticipantRole::Optional->label())->toBe('Optional');
});
