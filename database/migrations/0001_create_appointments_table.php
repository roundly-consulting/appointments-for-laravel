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
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default(Status::default()->value);
            $table->json('meta')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->uuid('recurrence_group')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('starts_at');
            $table->index(['starts_at', 'ends_at']);
            $table->index('recurrence_group');
        });
    }

    private function table(): string
    {
        /** @var string $name */
        $name = config('appointments.table_names.appointments', 'appointments');

        return $name;
    }
};
