<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\ParticipantRole;

it('defaults to attendee', function (): void {
    expect(ParticipantRole::default())->toBe(ParticipantRole::Attendee);
});

it('derives readable labels from the trait', function (): void {
    expect(ParticipantRole::Organiser->readable())->toBe('Organiser')
        ->and(ParticipantRole::Organiser->label())->toBe('Organiser')
        ->and(ParticipantRole::Optional->readable())->toBe('Optional');
});

it('exposes the enums helper surface', function (): void {
    expect(ParticipantRole::values()->all())->toBe(['organiser', 'attendee', 'optional'])
        ->and(ParticipantRole::labels()->all())->toBe(['Organiser', 'Attendee', 'Optional'])
        ->and(ParticipantRole::validationRule())->toBe('in:organiser,attendee,optional')
        ->and(ParticipantRole::tryFromLabel('Attendee'))->toBe(ParticipantRole::Attendee);
});

it('no longer resolves the removed lang seam', function (): void {
    expect(trans('appointments::roles.organiser'))->toBe('appointments::roles.organiser');
});
