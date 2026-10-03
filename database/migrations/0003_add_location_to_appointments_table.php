<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Appointments\Support\AppointmentsConfig;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->appointmentsTable(), function (Blueprint $table): void {
            $table->string('location')->nullable()->after('timezone');
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->index(['latitude', 'longitude']);
        });
    }

    private function appointmentsTable(): string
    {
        // Not set (absent, null or blank) means the packaged name; anything else must be a string.
        return AppointmentsConfig::appointmentsTable();
    }
};
