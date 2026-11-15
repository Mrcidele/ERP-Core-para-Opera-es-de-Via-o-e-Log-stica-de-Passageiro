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
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->integer('next_preventive_maintenance_km')->nullable();
        });

        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_next_preventive_km_not_negative CHECK (next_preventive_maintenance_km >= 0)');

        // A trava do motorista passa a incluir as 11 horas de descanso depois de cada viagem:
        // duas viagens conflitam se uma começa antes de a outra ter terminado e descansado.
        // (timezone('UTC', ...) torna a expressão imutável, como o índice exige.)
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_driver_no_overlap');
        DB::statement(<<<'SQL'
            ALTER TABLE trips ADD CONSTRAINT trips_driver_rest
            EXCLUDE USING GIST (
                driver_id WITH =,
                tsrange(timezone('UTC', departure_time), timezone('UTC', arrival_time) + interval '11 hours', '[)') WITH &&
            )
            WHERE (status <> 'canceled' AND deleted_at IS NULL)
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_driver_rest');
        DB::statement(<<<'SQL'
            ALTER TABLE trips ADD CONSTRAINT trips_driver_no_overlap
            EXCLUDE USING GIST (driver_id WITH =, tstzrange(departure_time, arrival_time, '[)') WITH &&)
            WHERE (status <> 'canceled' AND deleted_at IS NULL)
            SQL);

        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_next_preventive_km_not_negative');

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn('next_preventive_maintenance_km');
        });
    }
};
