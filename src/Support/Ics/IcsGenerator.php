<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Support\Ics;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Appointments\Models\Appointment;

final class IcsGenerator
{
    private const CRLF = "\r\n";

    private const PRODID = '-//Roundly Consulting//Appointments for Laravel//EN';

    public function forAppointment(Appointment $appointment): string
    {
        return $this->wrap($this->event($appointment));
    }

    /**
     * @param  iterable<Appointment>  $appointments
     */
    public function forCollection(iterable $appointments): string
    {
        $events = [];

        foreach ($appointments as $appointment) {
            $events = [...$events, ...$this->event($appointment)];
        }

        return $this->wrap($events);
    }

    /**
     * @param  list<string>  $events
     */
    private function wrap(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'CALSCALE:GREGORIAN',
            ...$events,
            'END:VCALENDAR',
        ];

        return implode(self::CRLF, array_map($this->fold(...), $lines)).self::CRLF;
    }

    /**
     * @return list<string>
     */
    private function event(Appointment $appointment): array
    {
        $start = CarbonImmutable::instance($appointment->starts_at)->utc();
        $end = $appointment->ends_at !== null
            ? CarbonImmutable::instance($appointment->ends_at)->utc()
            : $start->addMinutes($appointment->durationInMinutes());

        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$this->uid($appointment),
            'DTSTAMP:'.$this->stamp(CarbonImmutable::now()->utc()),
            'DTSTART:'.$this->stamp($start),
            'DTEND:'.$this->stamp($end),
            'SUMMARY:'.$this->escape($appointment->name),
            'STATUS:'.$this->status($appointment->status),
        ];

        if ($appointment->description !== null && $appointment->description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape($appointment->description);
        }

        $location = $this->location($appointment);

        if ($location !== null) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        $coordinates = $appointment->coordinates;

        if ($coordinates !== null) {
            $lines[] = sprintf('GEO:%s;%s', $this->coordinate($coordinates->latitude), $this->coordinate($coordinates->longitude));
        }

        $organizer = $this->organizerEmail($appointment);

        if ($organizer !== null) {
            $lines[] = $this->calendarUser('ORGANIZER', $organizer['name'], $organizer['email']);
        }

        foreach ($this->attendees($appointment) as $attendee) {
            $lines[] = $attendee;
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function uid(Appointment $appointment): string
    {
        return sprintf('appointment-%s@roundly-consulting', $appointment->getKey());
    }

    private function stamp(CarbonImmutable $moment): string
    {
        return $moment->format('Ymd\THis\Z');
    }

    private function status(Status $status): string
    {
        return match ($status) {
            Status::Confirmed, Status::Completed => 'CONFIRMED',
            Status::Cancelled, Status::Declined, Status::NoShow => 'CANCELLED',
            Status::Pending => 'TENTATIVE',
        };
    }

    private function location(Appointment $appointment): ?string
    {
        if ($appointment->location !== null && $appointment->location !== '') {
            return $appointment->location;
        }

        $location = $appointment->meta?->get('location');

        return is_string($location) ? $location : null;
    }

    /**
     * The appointment's booking contact, used as the calendar ORGANIZER.
     *
     * @return array{name: ?string, email: string}|null
     */
    private function organizerEmail(Appointment $appointment): ?array
    {
        $contact = $appointment->primaryEmail();

        if ($contact === null) {
            return null;
        }

        // Contacts stores a missing name as '' (NOT NULL column), so `??` alone never reached the label.
        $name = $contact->name !== '' ? $contact->name : $contact->label;

        return ['name' => $name, 'email' => $contact->value];
    }

    /**
     * One `ATTENDEE;CN=…:mailto:…` line per participant with a primary contact email.
     *
     * ATTENDEE is a CAL-ADDRESS (RFC 5545 §3.3.3) — a URI, and in practice calendar clients
     * only act on `mailto:`. A participant with no email has no address to give, so it is left
     * out rather than emitted as an invalid value that strict clients reject the file over.
     *
     * @return list<string>
     */
    private function attendees(Appointment $appointment): array
    {
        $lines = [];

        foreach ($appointment->participants as $participant) {
            $related = $participant->participant;
            $email = $related instanceof Model ? $this->primaryEmailOf($related) : null;

            if ($related instanceof Model && $email !== null && $email !== '') {
                $lines[] = $this->calendarUser('ATTENDEE', $this->displayName($related), $email);
            }
        }

        return $lines;
    }

    /**
     * A participant's primary contact email, when its related model carries contacts.
     */
    private function primaryEmailOf(Model $model): ?string
    {
        if (! method_exists($model, 'primaryEmail')) {
            return null;
        }

        $contact = $model->primaryEmail();

        return $contact?->value;
    }

    private function displayName(Model $model): ?string
    {
        $name = $model->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Build an ORGANIZER/ATTENDEE line with an optional CN parameter and a mailto value.
     */
    private function calendarUser(string $property, ?string $name, string $email): string
    {
        $params = $name !== null && $name !== '' ? ';CN='.$this->param($name) : '';

        return $property.$params.':mailto:'.$this->param($email, quote: false);
    }

    /**
     * Sanitise an iCalendar parameter value, quoting it when it carries separators.
     */
    private function param(string $value, bool $quote = true): string
    {
        // A parameter value can hold neither a DQUOTE nor any control character (§3.1).
        $clean = str_replace('"', '', $this->withoutControls($value));

        if ($quote && (str_contains($clean, ':') || str_contains($clean, ';') || str_contains($clean, ','))) {
            return '"'.$clean.'"';
        }

        return $clean;
    }

    private function coordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    /**
     * Escape a TEXT value (RFC 5545 §3.3.11). Every line break — CRLF from a textarea, a lone CR
     * or LF — becomes one escaped `\n`, so no raw CR/LF can end the content line early; any other
     * control character TEXT cannot carry is dropped (a tab is allowed).
     */
    private function escape(string $value): string
    {
        $value = preg_replace('/\r\n?/', "\n", $value) ?? $value;
        $value = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $value) ?? $value;

        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $value,
        );
    }

    /** Strip every control character except a tab. */
    private function withoutControls(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', '', $value) ?? $value;
    }

    /**
     * Fold lines longer than 75 octets per RFC 5545 (continuation lines start
     * with a single space).
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $current = '';
        // The first line may use the full 75 octets; continuation lines start
        // with a leading space that itself counts toward the limit, so they
        // carry at most 74 octets of content.
        $limit = 75;

        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $chunks[] = $current;
                $current = '';
                $limit = 74;
            }

            $current .= $char;
        }

        $chunks[] = $current;

        return implode(self::CRLF.' ', $chunks);
    }
}
