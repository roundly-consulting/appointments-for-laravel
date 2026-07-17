<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Fixtures;

use RoundlyConsulting\Appointments\Tests\Models\CustomAppointment;
use RoundlyConsulting\Appointments\Tests\Models\CustomParticipant;
use RoundlyConsulting\Appointments\Tests\TestCase;

/**
 * The base case for `tests/Configured` — the suite booted as a host that has swapped BOTH
 * appointments model seams in its own `config/appointments.php`.
 *
 * Boot order is the whole point, and it is why this is a separate base case (and therefore a
 * separate directory — Pest binds a test case per DIRECTORY, not per file). The existing
 * swap check in ServiceProviderTest sets `appointments.model` inside the test body: that
 * reads back correctly and proves almost nothing, because by the time the body runs the
 * provider has already hung its observers on the packaged class. A real host sets the key
 * before boot; so does this.
 *
 * This matters more here than anywhere else in the batch. Appointments is the package whose
 * `hasMany` FK was derived from the parent CLASS NAME — so a host swapping Appointment for
 * CustomAppointment got a query for `custom_appointment_id`, a column that does not exist.
 * Only a before-boot swap can see that.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * the base's own wiring, with no error and no red — the same decapitation an un-parented
 * `defineEnvironment()` override causes one level up.
 *
 * @see TestCase
 */
abstract class SwappedModelsTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'appointments.model' => CustomAppointment::class,
            'appointments.participant' => CustomParticipant::class,
        ]);
    }
}
