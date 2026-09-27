# Changelog

All notable changes to `appointments-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Appointments with start, duration or end time and a time zone, created through the fluent
  `Appointments::for()` builder or `CreateAppointmentAction` with an `AppointmentData` DTO.
- Participants of any Eloquent model with roles, via a polymorphic relation and the
  `HasAppointments` trait.
- Guarded status lifecycle: `confirm()`, `cancel()`, `complete()`, `decline()` and `markNoShow()`.
- Double-booking detection with `ConflictDetector` and the opt-in `preventConflicts()` guard.
- `Appointments::reschedule()` and recurring series (`RecurrenceData`, `createRecurring()`).
- Query scopes `upcoming()`, `past()`, `between()`, `overlapping()`, `withStatus()` and
  `forParticipant()`.
- Standards-compliant calendar export: `toIcs()` for one appointment, `IcsGenerator` for many.
- Events for appointment create, update, reschedule and status changes, and participant changes.
- Venue location with `located()`, `distanceFrom()` and `withinRadius()`, built on
  geolocation-for-laravel.
- Guest contact e-mail and phone on a booking, built on contacts-for-laravel.
- Booking approval workflows (quorum, stages or a named preset), built on approvals-for-laravel.
- Post-visit reviews with verified attendance and rating summaries, built on reviews-for-laravel.
