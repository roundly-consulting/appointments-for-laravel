<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\Frequency;

it('exposes the enums helper surface', function (): void {
    expect(Frequency::values()->all())->toBe(['daily', 'weekly', 'monthly'])
        ->and(Frequency::labels()->all())->toBe(['Daily', 'Weekly', 'Monthly'])
        ->and(Frequency::validationRule())->toBe('in:daily,weekly,monthly')
        ->and(Frequency::Weekly->readable())->toBe('Weekly')
        ->and(Frequency::tryFromLabel('Monthly'))->toBe(Frequency::Monthly);
});
