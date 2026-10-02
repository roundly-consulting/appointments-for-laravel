<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;

function icsUid(Appointment $appointment): string
{
    preg_match('/^UID:(.+)$/m', $appointment->toIcs(), $matches);

    return trim($matches[1] ?? '');
}

it('gives every appointment a stored, globally unique ICS UID', function (): void {
    config()->set('app.url', 'https://clinic.example.com');

    $first = Appointments::schedule('First')->startingAt('2026-07-01 09:00')->create();
    $second = Appointments::schedule('Second')->startingAt('2026-07-01 10:00')->create();

    expect($first->uuid)->toBeString()->not->toBe('')
        ->and(icsUid($first))->toBe($first->uuid.'@clinic.example.com')
        ->and(icsUid($second))->toBe($second->uuid.'@clinic.example.com')
        ->and(icsUid($first))->not->toBe(icsUid($second))
        // Stable: the UID is stored, so the same appointment exports the same UID again.
        ->and(icsUid($first->fresh()))->toBe(icsUid($first));
});

it('never reuses a UID when an id is reused, as after a re-seed', function (): void {
    $before = Appointments::schedule('Seeded')->startingAt('2026-07-01 09:00')->create();
    $uid = icsUid($before);
    $id = $before->getKey();
    $before->forceDelete();

    $after = Appointment::factory()->create(['id' => $id]);

    expect($after->getKey())->toBe($id)
        ->and(icsUid($after))->not->toBe($uid);
});

it('falls back to a fixed domain when app.url has no host', function (): void {
    config()->set('app.url', null);

    $appointment = Appointments::schedule('Hostless')->startingAt('2026-07-01 09:00')->create();

    expect(icsUid($appointment))->toBe($appointment->uuid.'@appointments-for-laravel');
});
