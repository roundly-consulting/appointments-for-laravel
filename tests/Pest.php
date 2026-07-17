<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Tests\Fixtures\SwappedModelsTestCase;
use RoundlyConsulting\Appointments\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the Configured directory below needs a different base
// case (both model seams swapped BEFORE boot) and a blanket bind would claim it first —
// Pest binds a test case per directory, not per file. ArchTest.php is listed because
// `swappableModelsAreNotFinal` reads the two `appointments.…` config defaults and so needs
// the app booted; an arch file is not automatically test-cased.
uses(TestCase::class)->in(
    'ArchTest.php',
    'AppointmentBuilderTest.php',
    'AppointmentManagerTest.php',
    'AppointmentStatusTest.php',
    'AppointmentTest.php',
    'ParticipantTest.php',
    'ServiceProviderTest.php',
    'Actions',
    'Approvals',
    'Concerns',
    'Contacts',
    'DataTransferObjects',
    'Enums',
    'Exceptions',
    'Geolocation',
    'Migrations',
    'Provider',
    'Reviews',
    'Scopes',
    'Support',
);

// The model-swap proofs need both `appointments.…` model keys pointed at host subclasses
// BEFORE the providers boot, so they run on their own base case in their own directory.
uses(SwappedModelsTestCase::class)->in('Configured');
