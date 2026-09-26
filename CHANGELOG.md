# Changelog

All notable changes to `appointments-for-laravel` will be documented in this file.

## Unreleased

### Fixed

- Recurring series now give every occurrence the location, coordinates, guest contacts and
  approval request a single scheduled appointment gets (they were silently dropped).
- A recurring series is created atomically: a conflict on a later occurrence no longer leaves
  the earlier ones behind.
