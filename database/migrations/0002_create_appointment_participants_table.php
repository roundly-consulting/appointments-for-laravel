<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Support\Config;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('appointments.key_type');

        Schema::create($this->participantsTable(), function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('appointment_id');
            $table->morphKey('participant', $keyType, nullable: false);
            $table->string('role')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['appointment_id', 'participant_type', 'participant_id']);
        });
    }

    private function participantsTable(): string
    {
        // Absent means the packaged name; anything present must be a non-empty string.
        return config('appointments.table_names.participants') === null ? 'appointment_participants' : Config::requireString('appointments.table_names.participants');
    }
};
