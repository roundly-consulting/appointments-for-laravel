<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Contacts\Enums\ContactType;

it('attaches guest contacts through the builder', function (): void {
    $appointment = Appointments::schedule('Guest booking')
        ->startingAt('2026-07-01 09:00')
        ->withContactEmail('guest@example.com')
        ->withContactPhone('+441234567890')
        ->create();

    expect($appointment->primaryEmail()?->value)->toBe('guest@example.com')
        ->and($appointment->primaryPhone()?->value)->toBe('+441234567890')
        ->and($appointment->contactsOfType(ContactType::Email))->toHaveCount(1);
});

it('adds a contact directly on the appointment', function (): void {
    $appointment = Appointments::schedule('Walk-in')->startingAt('2026-07-01 09:00')->create();

    $appointment->addEmail('walkin@example.com', primary: true);

    expect($appointment->primaryEmail()?->value)->toBe('walkin@example.com');
});
