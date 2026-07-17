<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->participantsTable(), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id');
            $table->morphs('participant');
            $table->string('role')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['appointment_id', 'participant_type', 'participant_id']);
        });
    }

    private function participantsTable(): string
    {
        /** @var string $name */
        $name = config('appointments.table_names.participants', 'appointment_participants');

        return $name;
    }
};
