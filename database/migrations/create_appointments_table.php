<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Appointments\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default(Status::New->value);
            $table->json('meta')->nullable();
            $table->timestamp('appointment_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
