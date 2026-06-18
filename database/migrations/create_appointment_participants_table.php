<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Appointments\Models\Appointment;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Appointment::class);
            $table->morphs('participant');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['appointment_id', 'participant_type', 'participant_id']);
        });
    }
};
