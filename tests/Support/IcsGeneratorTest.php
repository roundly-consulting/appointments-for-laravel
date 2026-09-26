<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Actions\AttachParticipantAction;
use RoundlyConsulting\Appointments\DataTransferObjects\ParticipantData;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Support\Ics\IcsGenerator;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;

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

/**
 * The ICS as a list of content lines: physical lines unfolded (RFC 5545 §3.1), split on CRLF.
 *
 * @return list<string>
 */
function icsContentLines(string $ics): array
{
    return explode("\r\n", rtrim(str_replace("\r\n ", '', $ics), "\r\n"));
}

it('normalises CRLF, CR and LF line breaks in text to one escaped newline', function (): void {
    // A textarea submits CRLF; a stray CR used to end up raw inside the content line.
    $appointment = Appointment::factory()->create([
        'name' => "Mac\rclassic",
        'description' => "Line one\r\nLine two\rLine three\nEnd",
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    $ics = $appointment->toIcs();

    // Every CR and every LF in the stream belongs to a CRLF line break.
    expect(preg_match('/\r(?!\n)|(?<!\r)\n/', $ics))->toBe(0)
        ->and(icsContentLines($ics))->toContain('DESCRIPTION:Line one\\nLine two\\nLine three\\nEnd')
        ->and(icsContentLines($ics))->toContain('SUMMARY:Mac\\nclassic');
});

it('drops control characters iCalendar text cannot carry, keeping tabs', function (): void {
    $appointment = Appointment::factory()->create([
        'name' => "Bell\x07 and\x1B escape\tTab\x7F",
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    expect(icsContentLines($appointment->toIcs()))->toContain("SUMMARY:Bell and escape\tTab");
});

it('folds multibyte text without splitting a UTF-8 sequence', function (): void {
    $description = str_repeat('Žltá ruža € 😀 ', 20);

    $appointment = Appointment::factory()->create([
        'description' => $description,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);

    $ics = $appointment->toIcs();

    foreach (explode("\r\n", rtrim($ics, "\r\n")) as $physical) {
        expect(strlen($physical))->toBeLessThanOrEqual(75)
            ->and(mb_check_encoding($physical, 'UTF-8'))->toBeTrue();
    }

    expect(icsContentLines($ics))->toContain('DESCRIPTION:'.$description);
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
    $attendee = User::create();
    $attendee->addEmail('guest@example.com', primary: true);
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($attendee));
    $appointment->load('participants');

    $ics = $this->generator->forAppointment($appointment);

    expect($ics)->toContain('LOCATION:HQ')->toContain('ATTENDEE:mailto:guest@example.com');
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

it('keeps line breaks and control characters out of a CN parameter', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    $appointment->addContact(new ContactData(
        type: ContactType::Email,
        value: 'desk@example.com',
        name: "Front\r\ndesk\x07; \"main\"",
        isPrimary: true,
    ));

    expect(icsContentLines($appointment->toIcs()))
        ->toContain('ORGANIZER;CN="Frontdesk; main":mailto:desk@example.com');
});

it('names the organizer by the contact label when the contact has no name', function (): void {
    // Contacts stores a missing name as '' (the column is NOT NULL), not null.
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    $appointment->addEmail('desk@example.com', label: 'Front desk', primary: true);

    expect(icsContentLines($appointment->toIcs()))->toContain('ORGANIZER;CN=Front desk:mailto:desk@example.com');
});

it('omits a participant without a contact email rather than emit an invalid attendee', function (): void {
    // ATTENDEE is a CAL-ADDRESS (RFC 5545 §3.3.3): a URI, in practice mailto:. A participant with
    // no email has no address a calendar client can use, so it is left out of the file.
    $withEmail = User::create();
    $withEmail->addEmail('guest@example.com', primary: true);

    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData(User::create()));
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData($withEmail));
    $appointment->load('participants');

    $attendees = array_values(array_filter(
        icsContentLines($appointment->toIcs()),
        fn (string $line): bool => str_starts_with($line, 'ATTENDEE'),
    ));

    expect($attendees)->toBe(['ATTENDEE:mailto:guest@example.com']);
});

/** A participant model with no contacts at all (no `primaryEmail()`). */
final class IcsRoom extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

it('omits a participant whose model carries no contacts', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00', 'UTC'),
        'duration_minutes' => 60,
    ]);
    app(AttachParticipantAction::class)->execute($appointment, new ParticipantData(IcsRoom::create()));
    $appointment->load('participants');

    expect($appointment->participants)->toHaveCount(1)
        ->and($appointment->toIcs())->not->toContain('ATTENDEE');
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
