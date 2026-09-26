# Changelog

All notable changes to `appointments-for-laravel` will be documented in this file.

## Unreleased

### Fixed

- Recurring series now give every occurrence the location, coordinates, guest contacts and
  approval request a single scheduled appointment gets (they were silently dropped).
- A recurring series is created atomically: a conflict on a later occurrence no longer leaves
  the earlier ones behind.
- ICS text escaping follows RFC 5545 §3.3.11: a CR or CRLF (e.g. from a textarea) becomes one
  escaped `\n` instead of a raw CR inside the content line; other control characters are dropped.
- The ICS `ORGANIZER` `CN` falls back to the booking contact's label when it has no name, and CN
  values no longer carry control characters.
