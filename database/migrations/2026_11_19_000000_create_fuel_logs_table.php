<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('odometer_km');
            // Volume em mililitros e custo em centavos: inteiros, sem erro de arredondamento.
            $table->unsignedInteger('liters_milliliters');
            $table->unsignedInteger('total_cost_cents');
            $table->string('station_name', 120);
            $table->timestampTz('fueled_at');
            $table->timestampsTz();
            $table->softDeletesTz();

            // Histórico de um veículo em ordem de data: listagem, último abastecimento e rendimento.
            $table->index(['vehicle_id', 'fueled_at']);
            // Gasto do mês no painel.
            $table->index('fueled_at');
        });

        DB::statement('ALTER TABLE fuel_logs ADD CONSTRAINT fuel_logs_odometer_not_negative CHECK (odometer_km >= 0)');
        DB::statement('ALTER TABLE fuel_logs ADD CONSTRAINT fuel_logs_liters_positive CHECK (liters_milliliters > 0)');
        DB::statement('ALTER TABLE fuel_logs ADD CONSTRAINT fuel_logs_cost_not_negative CHECK (total_cost_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
