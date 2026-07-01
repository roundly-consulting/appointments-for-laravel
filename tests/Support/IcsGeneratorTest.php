<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\Ics\IcsGenerator;
use RoundlyConsulting\Appointments\Tests\Models\User;

beforeEach(function (): void {
    $this->generator = app(IcsGenerator::class);
});

it('produces a structurally valid VCALENDAR for one appointment', function (): void {
    $appointment = Appointment::factory()->create([
        'name' => 'Kickoff',
        'description' => 'Notes',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00', 'UTC'),
    ]);

    $ics = $appointment->toIcs();

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('END:VEVENT')
        ->toContain('SUMMARY:Kickoff')
        ->toContain('DESCRIPTION:Notes')
        ->toContain('DTSTART:20260701T090000Z')
        ->toContain('DTEND:20260701T100000Z')
        ->toContain("\r\n");
});

it('maps statuses to the ICS vocabulary', function (Status $status, string $expected): void {
    $appointment = Appointment::factory()->withStatus($status)->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    expect($appointment->toIcs())->toContain('STATUS:'.$expected);
})->with([
    'pending' => [Status::Pending, 'TENTATIVE'],
    'confirmed' => [Status::Confirmed, 'CONFIRMED'],
    'completed' => [Status::Completed, 'CONFIRMED'],
    'cancelled' => [Status::Cancelled, 'CANCELLED'],
    'declined' => [Status::Declined, 'CANCELLED'],
    'no_show' => [Status::NoShow, 'CANCELLED'],
]);

it('escapes commas, semicolons, backslashes and newlines', function (): void {
    $appointment = Appointment::factory()->create([
        'name' => "A, B; C\\D\nE",
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    expect($appointment->toIcs())->toContain('SUMMARY:A\\, B\\; C\\\\D\\nE');
});

it('derives DTEND from duration when ends_at is missing', function (): void {
    $appointment = Appointment::factory()->make([
        'id' => 1,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 45,
        'ends_at' => null,
    ]);

    expect($this->generator->forAppointment($appointment))->toContain('DTEND:20260701T094500Z');
});

it('includes a location from meta and attendee lines from participants', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
        'meta' => ['location' => 'HQ'],
    ]);
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData(User::create()));
    $appointment->load('participants');

    $ics = $this->generator->forAppointment($appointment);

    expect($ics)->toContain('LOCATION:HQ')->toContain('ATTENDEE:');
});

it('prefers the location column and emits a GEO line', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
        'meta' => ['location' => 'Old meta venue'],
        'location' => 'Clinic A',
        'latitude' => 51.5074,
        'longitude' => -0.1278,
    ]);

    $ics = $appointment->toIcs();

    expect($ics)->toContain('LOCATION:Clinic A')
        ->not->toContain('Old meta venue')
        ->and($ics)->toContain('GEO:51.5074;-0.1278');
});

it('upgrades an attendee to a mailto line when the participant has a contact email', function (): void {
    $attendee = User::create();
    $attendee->addEmail('attendee@example.com', primary: true);

    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($attendee));
    $appointment->load('participants');

    expect($appointment->toIcs())->toContain('ATTENDEE:mailto:attendee@example.com');
});

it('emits an ORGANIZER line from the appointment booking contact', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    $appointment->addEmail('organiser@example.com', primary: true);

    expect($appointment->toIcs())->toContain('ORGANIZER:mailto:organiser@example.com');
});

it('falls back to a type:id attendee when no contact email exists', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData(User::create()));
    $appointment->load('participants');

    expect($appointment->toIcs())->toMatch('/ATTENDEE:[^\r\n]+:\d+/');
});

it('builds a calendar from a collection of appointments', function (): void {
    Appointment::factory()->count(2)->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    $ics = $this->generator->forCollection(Appointment::all());

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2)
        ->and(substr_count($ics, 'BEGIN:VCALENDAR'))->toBe(1);
});

it('folds long lines at 75 octets', function (): void {
    $appointment = Appointment::factory()->create([
        'name' => str_repeat('x', 200),
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    foreach (explode("\r\n", $appointment->toIcs()) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});
