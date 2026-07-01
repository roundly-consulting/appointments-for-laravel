<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\Status;
use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;

it('defaults to pending', function (): void {
    expect(Status::default())->toBe(Status::Pending);
});

it('exposes the enums helper surface', function (): void {
    expect(Status::values()->all())->toBe([
        'pending', 'confirmed', 'cancelled', 'completed', 'declined', 'no_show',
    ]);

    expect(Status::validationRule())->toBe('in:pending,confirmed,cancelled,completed,declined,no_show');

    expect(Status::labels()->all())->toBe([
        'Pending', 'Confirmed', 'Cancelled', 'Completed', 'Declined', 'No Show',
    ]);

    expect(Status::toOptions()->all())->toBe([
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'declined' => 'Declined',
        'no_show' => 'No Show',
    ]);
});

it('builds option DTOs', function (): void {
    $options = Status::options();

    expect($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('pending')
        ->and($options->first()->label)->toBe('Pending')
        ->and($options->first()->name)->toBe('Pending');
});

it('derives readable labels from the trait', function (): void {
    expect(Status::Confirmed->readable())->toBe('Confirmed')
        ->and(Status::Confirmed->label())->toBe('Confirmed')
        ->and(Status::NoShow->readable())->toBe('No Show');
});

it('looks a case up by its label', function (): void {
    expect(Status::tryFromLabel('No Show'))->toBe(Status::NoShow)
        ->and(Status::tryFromLabel('Nope'))->toBeNull();
});

it('exposes a colour for every case', function (): void {
    foreach (Status::cases() as $status) {
        expect($status->color())->toBeString()->not->toBeEmpty();
    }
});

it('allows documented transitions', function (Status $from, Status $to): void {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    'pending → confirmed' => [Status::Pending, Status::Confirmed],
    'pending → declined' => [Status::Pending, Status::Declined],
    'pending → cancelled' => [Status::Pending, Status::Cancelled],
    'confirmed → completed' => [Status::Confirmed, Status::Completed],
    'confirmed → cancelled' => [Status::Confirmed, Status::Cancelled],
    'confirmed → no_show' => [Status::Confirmed, Status::NoShow],
]);

it('rejects illegal transitions', function (Status $from, Status $to): void {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'pending → completed' => [Status::Pending, Status::Completed],
    'pending → no_show' => [Status::Pending, Status::NoShow],
    'confirmed → declined' => [Status::Confirmed, Status::Declined],
    'completed → confirmed' => [Status::Completed, Status::Confirmed],
    'cancelled → confirmed' => [Status::Cancelled, Status::Confirmed],
    'declined → pending' => [Status::Declined, Status::Pending],
    'no_show → completed' => [Status::NoShow, Status::Completed],
]);

it('knows which statuses are final', function (): void {
    expect(Status::Pending->isFinal())->toBeFalse()
        ->and(Status::Confirmed->isFinal())->toBeFalse()
        ->and(Status::Completed->isFinal())->toBeTrue()
        ->and(Status::Cancelled->isFinal())->toBeTrue()
        ->and(Status::Declined->isFinal())->toBeTrue()
        ->and(Status::NoShow->isFinal())->toBeTrue();
});

it('no longer resolves the removed lang seam', function (): void {
    expect(trans('appointments::status.pending'))->toBe('appointments::status.pending');
});
