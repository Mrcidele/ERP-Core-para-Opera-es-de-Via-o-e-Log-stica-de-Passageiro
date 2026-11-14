<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('line_id')->constrained('lines')->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('driver_id')->constrained('employees')->restrictOnDelete();
            // Horários sempre em UTC.
            $table->dateTime('departure_time');
            $table->dateTime('arrival_time');
            $table->string('status', 20)->index();
            $table->timestamps();
            $table->softDeletes();

            // Apoiam a consulta de conflito de horário por veículo e por motorista.
            $table->index(['vehicle_id', 'departure_time', 'arrival_time']);
            $table->index(['driver_id', 'departure_time', 'arrival_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
