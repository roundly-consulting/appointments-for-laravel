<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/appointments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel">
    <img src="art/hero.png" alt="Appointments for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/appointments-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/appointments-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/appointments-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/appointments-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/appointments-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/appointments-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Appointments for Laravel

A scheduling toolkit for Laravel: book appointments with durations and time zones, attach any
Eloquent model as a participant, prevent double-bookings, drive a guarded status lifecycle,
generate recurring series and export standards-compliant `.ics` calendars. Venues, guest
contacts, booking approvals and post-visit reviews come built in.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/appointments-for-laravel
php artisan vendor:publish --tag="appointments-migrations"
php artisan vendor:publish --tag="approvals-migrations" --tag="contacts-migrations" --tag="reviews-migrations" --tag="media-migrations"
php artisan migrate
```

The migrations are not loaded automatically, and the companion packages' migrations are needed
too. If your participant models have UUID/ULID keys, set `APPOINTMENTS_KEY_TYPE` **before**
migrating.

## Usage

Book an appointment with its participants:

```php
use RoundlyConsulting\Appointments\Enums\ParticipantRole;
use RoundlyConsulting\Appointments\Facades\Appointments;

$appointment = Appointments::schedule('Project kickoff')
    ->startingAt('2026-07-01 17:30', timezone: 'Europe/Bratislava')
    ->lasting(90)                                   // minutes; or ->until('2026-07-01 19:00')
    ->withParticipant($host, ParticipantRole::Organiser)
    ->withParticipant($guest)
    ->preventConflicts()                            // refuse a double-booking
    ->create();
```

Then move it through its lifecycle and export it:

```php
use Carbon\CarbonImmutable;

$booking = Appointments::for($appointment);

$booking->confirm();                                                // pending → confirmed
$booking->reschedule(CarbonImmutable::parse('2026-07-02 10:00'));   // keeps the duration
$booking->participants()->add($colleague);

Appointments::isAvailable($guest, $appointment->starts_at, $appointment->ends_at); // false
$booking->ics();                                                    // an RFC 5545 calendar
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/appointments-for-laravel](https://roundly-consulting.com/open-source/docs/appointments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=appointments-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
