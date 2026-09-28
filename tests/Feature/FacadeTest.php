<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Facades\Appointments;

/**
 * The Actions → Manager → Facade contract, pinned: the facade documents every public
 * AppointmentManager method (the handle bodies are @internal), `Appointments::fake()` swaps in an
 * AppointmentsFake that subtypes the manager (so injected managers get it too), and every action
 * under src/Actions is reachable from the facade.
 */
it('pins the appointments facade contract', function (): void {
    expect(Appointments::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});
