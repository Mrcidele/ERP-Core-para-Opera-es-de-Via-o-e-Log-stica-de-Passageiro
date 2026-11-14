<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Trava no banco contra agendamentos simultâneos: mesmo que duas requisições passem
     * juntas pela checagem da ScheduleTripAction, só uma consegue gravar.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            ALTER TABLE trips ADD CONSTRAINT trips_vehicle_no_overlap
            EXCLUDE USING GIST (vehicle_id WITH =, tstzrange(departure_time, arrival_time, '[)') WITH &&)
            WHERE (status <> 'canceled' AND deleted_at IS NULL)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE trips ADD CONSTRAINT trips_driver_no_overlap
            EXCLUDE USING GIST (driver_id WITH =, tstzrange(departure_time, arrival_time, '[)') WITH &&)
            WHERE (status <> 'canceled' AND deleted_at IS NULL)
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_driver_no_overlap');
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_vehicle_no_overlap');
    }
};
