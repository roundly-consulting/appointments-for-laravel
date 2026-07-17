<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned table the appointment fixtures live in. Built by hand in
 * TestCase::defineDatabaseMigrations() before this row; a migration now so PackageTestCase
 * can own the whole schema through `migrationSources()` — and so the real-engine reset
 * (drop-all-tables + re-migrate) restores it, which an inline Schema::create() would not
 * survive on Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }
};
