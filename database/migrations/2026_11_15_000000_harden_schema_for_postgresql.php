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
        // Horários de viagem passam a timestamptz; os valores já gravados estavam em UTC.
        DB::statement("ALTER TABLE trips ALTER COLUMN departure_time TYPE timestamptz(0) USING departure_time AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE trips ALTER COLUMN arrival_time TYPE timestamptz(0) USING arrival_time AT TIME ZONE 'UTC'");

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropIndex(['vehicle_id', 'departure_time', 'arrival_time']);
            $table->dropIndex(['driver_id', 'departure_time', 'arrival_time']);

            $table->index(['vehicle_id', 'status', 'departure_time']);
            $table->index(['driver_id', 'status', 'departure_time']);
        });

        DB::statement('ALTER TABLE trips ADD CONSTRAINT trips_arrival_after_departure CHECK (arrival_time > departure_time)');
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_mileage_not_negative CHECK (current_mileage >= 0)');
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_capacity_positive CHECK (capacity > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_capacity_positive');
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_mileage_not_negative');
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_arrival_after_departure');

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropIndex(['vehicle_id', 'status', 'departure_time']);
            $table->dropIndex(['driver_id', 'status', 'departure_time']);

            $table->index(['vehicle_id', 'departure_time', 'arrival_time']);
            $table->index(['driver_id', 'departure_time', 'arrival_time']);
        });

        DB::statement("ALTER TABLE trips ALTER COLUMN departure_time TYPE timestamp(0) USING departure_time AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE trips ALTER COLUMN arrival_time TYPE timestamp(0) USING arrival_time AT TIME ZONE 'UTC'");
    }
};
