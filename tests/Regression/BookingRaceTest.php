<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Appointments\Exceptions\DuplicateParticipantException;
use RoundlyConsulting\Appointments\Facades\Appointments;
use RoundlyConsulting\Appointments\Models\Appointment;
use RoundlyConsulting\Appointments\Tests\Models\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/*
 * `preventConflicts` was check-then-insert with no transaction: two concurrent bookings of one
 * host could both run the conflict query, both find nothing and both insert. Now the booking takes
 * a row lock on every participant (and on the appointment, when one is changed) and only then
 * looks for a clash, inside the transaction that writes — so bookings of one person serialize and
 * the second one's conflict query sees the first one's committed row.
 *
 * On SQLite the lock compiles to nothing, so the suite installs the fleet's LockRecordingGrammar,
 * which renders it as a `/* lock-for-update *\/` marker; a real engine renders `for update`.
 */

/**
 * Every statement the closure runs, lower-cased and unquoted, with the transaction level it ran at.
 *
 * @return list<array{sql: string, level: int}>
 */
function bookingStatements(callable $callback): array
{
    $connection = DB::connection();

    if ($connection->getDriverName() === 'sqlite') {
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    }

    $log = [];

    DB::listen(function (QueryExecuted $query) use (&$log): void {
        $log[] = [
            'sql' => str_replace(['"', '`'], '', strtolower($query->sql)),
            'level' => $query->connection->transactionLevel(),
        ];
    });

    $callback();

    return $log;
}

function isRowLock(string $sql, string $table): bool
{
    return str_starts_with($sql, 'select')
        && str_contains($sql, ' from '.$table.' ')
        && (str_contains($sql, 'lock-for-update') || str_contains($sql, 'for update'));
}

/**
 * @param  list<array{sql: string, level: int}>  $log
 */
function firstIndexOf(array $log, callable $match): int
{
    foreach ($log as $index => $entry) {
        if ($match($entry['sql'])) {
            return $index;
        }
    }

    return -1;
}

/**
 * @param  list<array{sql: string, level: int}>  $log
 * @param  list<int>  $indexes
 */
function expectInOrderInsideATransaction(array $log, array $indexes): void
{
    $previous = -1;

    foreach ($indexes as $index) {
        expect($index)->toBeGreaterThan($previous)
            ->and($log[$index]['level'])->toBeGreaterThan(0);

        $previous = $index;
    }
}

function isConflictQuery(string $sql): bool
{
    return str_starts_with($sql, 'select') && str_contains($sql, ' from appointments ') && str_contains($sql, 'starts_at <');
}

it('locks every participant before the conflict check and the insert, in one transaction', function (): void {
    $host = User::create();
    $guest = User::create();

    $log = bookingStatements(fn () => Appointments::schedule('Booked')
        ->startingAt('2026-07-01 10:00')
        ->withParticipant($host)
        ->withParticipant($guest)
        ->preventConflicts()
        ->create());

    $locks = array_keys(array_filter($log, fn (array $entry): bool => isRowLock($entry['sql'], 'users')));
    $conflict = firstIndexOf($log, fn (string $sql): bool => isConflictQuery($sql));
    $insert = firstIndexOf($log, fn (string $sql): bool => str_starts_with($sql, 'insert into appointments '));

    expect($locks)->toHaveCount(2);
    expectInOrderInsideATransaction($log, [...$locks, $conflict, $insert]);
});

it('locks the appointment, then the newcomer, before checking an added participant', function (): void {
    $appointment = Appointments::schedule('Booked')->startingAt('2026-07-01 10:00')->create();
    $user = User::create();

    $log = bookingStatements(fn () => Appointments::for($appointment)->participants()->add($user, preventConflicts: true));

    $appointmentLock = firstIndexOf($log, fn (string $sql): bool => isRowLock($sql, 'appointments'));
    $userLock = firstIndexOf($log, fn (string $sql): bool => isRowLock($sql, 'users'));
    $conflict = firstIndexOf($log, fn (string $sql): bool => isConflictQuery($sql));
    $insert = firstIndexOf($log, fn (string $sql): bool => str_starts_with($sql, 'insert into appointment_participants '));

    expect($appointmentLock)->toBeGreaterThanOrEqual(0);
    expectInOrderInsideATransaction($log, [$appointmentLock, $userLock, $conflict, $insert]);
});

it('locks the appointment, then its participants, before checking a reschedule', function (): void {
    $host = User::create();
    $appointment = Appointments::schedule('Booked')->startingAt('2026-07-01 10:00')->withParticipant($host)->create();

    $log = bookingStatements(fn () => Appointments::for($appointment)->reschedule(CarbonImmutable::parse('2026-07-02 10:00'), preventConflicts: true));

    $appointmentLock = firstIndexOf($log, fn (string $sql): bool => isRowLock($sql, 'appointments'));
    $userLock = firstIndexOf($log, fn (string $sql): bool => isRowLock($sql, 'users'));
    $conflict = firstIndexOf($log, fn (string $sql): bool => isConflictQuery($sql));
    $update = firstIndexOf($log, fn (string $sql): bool => str_starts_with($sql, 'update appointments '));

    expect($appointmentLock)->toBeGreaterThanOrEqual(0);
    expectInOrderInsideATransaction($log, [$appointmentLock, $userLock, $conflict, $update]);
});

it('takes no lock when conflicts are not prevented', function (): void {
    $log = bookingStatements(fn () => Appointments::schedule('Booked')
        ->startingAt('2026-07-01 10:00')
        ->withParticipant(User::create())
        ->create());

    expect(firstIndexOf($log, fn (string $sql): bool => isRowLock($sql, 'users')))->toBe(-1)
        ->and(firstIndexOf($log, fn (string $sql): bool => str_starts_with($sql, 'insert into appointments ')))->toBeGreaterThanOrEqual(0);
});

it('locks a participant that lives on another connection in a transaction of its own', function (): void {
    config()->set('database.connections.people', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Schema::connection('people')->create('users', function (Blueprint $table): void {
        $table->id();
    });

    $person = (new User)->setConnection('people');
    $person->save();

    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels): void {
        if ($query->connectionName === 'people') {
            $levels[] = $query->connection->transactionLevel();
        }
    });

    $appointment = Appointments::schedule('Cross-connection')
        ->startingAt('2026-07-01 10:00')
        ->withParticipant($person)
        ->preventConflicts()
        ->create();

    expect($appointment->exists)->toBeTrue()
        ->and($levels)->not->toBeEmpty()
        ->and(min($levels))->toBeGreaterThan(0);

    DB::purge('people');
});

/*
 * The real-engine proof: while another session holds the host's row lock (a booking in flight),
 * a booking for that host waits on the lock — here it gives up after the lock timeout — before it
 * runs its conflict query, and nothing is written.
 */
it('waits for the participant row lock before looking for a clash', function (): void {
    $host = User::create();

    config()->set('database.connections.rival', config('database.connections.'.config('database.default')));
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table('users')->where('id', $host->getKey())->lockForUpdate()->first();

    DriverMatrix::driver() === 'pgsql'
        ? DB::statement("set lock_timeout = '300ms'")
        : DB::statement('set session innodb_lock_wait_timeout = 1');

    try {
        Appointments::schedule('Racing')->startingAt('2026-07-01 10:00')->withParticipant($host)->preventConflicts()->create();
        $this->fail('The booking did not wait for the participant row lock.');
    } catch (QueryException $e) {
        expect(strtolower($e->getSql()))->toContain('users')->toContain('for update');
    } finally {
        $rival->rollBack();
        DB::purge('rival');
    }

    expect(Appointment::query()->count())->toBe(0);
})->skip(fn (): bool => ! in_array(DriverMatrix::driver(), ['pgsql', 'mysql'], true), 'row locks need a real engine');

it('turns a concurrent add of the same model into a duplicate refusal', function (): void {
    $appointment = Appointments::schedule('Booked')->startingAt('2026-07-01 10:00')->create();
    $user = User::create();
    $raced = false;

    // A concurrent request adds the same user right after this one checked for an existing row.
    DB::listen(function (QueryExecuted $query) use (&$raced, $appointment, $user): void {
        if (! $raced && str_contains($query->sql, 'appointment_participants') && str_starts_with(strtolower($query->sql), 'select')) {
            $raced = true;
            DB::table('appointment_participants')->insert([
                'appointment_id' => $appointment->getKey(),
                'participant_type' => $user->getMorphClass(),
                'participant_id' => $user->getKey(),
            ]);
        }
    });

    expect(fn () => Appointments::for($appointment)->participants()->add($user))
        ->toThrow(DuplicateParticipantException::class);
});
