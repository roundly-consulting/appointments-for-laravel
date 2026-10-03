# Changelog

All notable changes to `appointments-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Appointments with start, duration or end time and a time zone, scheduled through the fluent
  `Appointments::schedule($name)` builder or `Appointments::create(AppointmentData)`, backed by an
  injectable `AppointmentManager` and one action per operation — facade, dependency injection and
  the raw action run the same code. Times are stored as UTC whatever `app.timezone` is; each
  appointment keeps the zone it was booked in. A booking is written in one transaction.
- `Appointments::for($appointment)` handle: `reschedule()`, `transition()`, `confirm()`,
  `cancel()`, `complete()`, `decline()`, `markNoShow()`, `ics()` and `participants()`.
- Participants of any Eloquent model with roles, via a polymorphic relation and the
  `HasAppointments` trait; `for($appointment)->participants()->add()/remove()/has()/all()`
  (`AttachParticipantAction`, `DetachParticipantAction`) with a duplicate guard
  (`DuplicateParticipantException`), an opt-in conflict guard, cross-appointment refusal
  (`ParticipantNotFoundException`) and restore-on-re-add.
- Guarded status lifecycle; the model shortcuts `confirm()`, `cancel()`, `complete()`,
  `decline()`, `markNoShow()` and `transitionTo()` go through the manager.
- Double-booking detection: `Appointments::conflicts()` / `isAvailable()` and the opt-in
  `preventConflicts` guard on create, reschedule and participant add, race-safe under row locks.
- Recurring series (`RecurrenceData`, `createRecurring()` on the builder and the facade),
  all-or-nothing and expanded in the appointment's timezone, and `Appointments::occurrences()` to
  preview a rule without writing.
- Query scopes `upcoming()`, `past()`, `between()`, `overlapping()`, `withStatus()` and
  `forParticipant()`.
- Standards-compliant calendar export: `Appointments::for($appointment)->ics()` / `toIcs()` for
  one appointment, `Appointments::ics($appointments)` for a feed, with a UID built from each
  appointment's stored `uuid`.
- Events for appointment create, update, reschedule and status changes, and participant changes,
  dispatched after the surrounding transaction commits.
- `Appointments::fake()`: a recording `AppointmentsFake` (a manager subtype, so injected managers
  get it too) that sees every write — including builder, handle and model-shortcut calls — with
  `assertScheduled`, `assertRescheduled`, `assertTransitioned`, `assertParticipantAdded`,
  `assertParticipantRemoved` and their `assertNothing*` / `assertNo*` counterparts.
- Venue location with `located()`, `distanceFrom()` and `withinRadius()`, built on
  geolocation-for-laravel.
- Guest contact e-mail and phone on a booking, built on contacts-for-laravel.
- Booking approval workflows (quorum, stages or a named preset), built on approvals-for-laravel,
  and the `appointments:expire-approvals` command, which lapses expired appointment approvals only.
- Post-visit reviews with verified attendance and rating summaries, built on reviews-for-laravel.
