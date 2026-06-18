<?php

declare(strict_types=1);

use RoundlyConsulting\Appointments\Enums\Status;

it('defaults to pending', function (): void {
    expect(Status::default())->toBe(Status::Pending);
});

it('resolves a translatable label', function (): void {
    expect(Status::Confirmed->label())->toBe('Confirmed')
        ->and(Status::NoShow->label())->toBe('No show');
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
