<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Frota de carga: o veículo passa a ter tipo de caminhão, modelo, carga útil em quilos e meta própria de rendimento.
 *
 * Registros existentes (classes antigas leito, semi_leito e executivo) viram bitruck, recebem a carga útil
 * típica de um bitruck e mantêm explicitamente a meta de 2,2 km/L que valia para todos até aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vehicles RENAME COLUMN class_type TO vehicle_type');
        DB::statement('ALTER TABLE vehicles RENAME COLUMN capacity TO payload_kg');
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_capacity_positive');
        DB::statement('ALTER TABLE vehicles ALTER COLUMN payload_kg TYPE integer');
        DB::statement('ALTER TABLE vehicles ADD COLUMN min_meters_per_liter integer NULL');
        DB::statement('ALTER TABLE vehicles ADD COLUMN model varchar(60) NULL');

        DB::statement("UPDATE vehicles SET vehicle_type = 'bitruck', payload_kg = 14000, min_meters_per_liter = 2200 WHERE vehicle_type IN ('leito', 'semi_leito', 'executivo')");

        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_vehicle_type_valid CHECK (vehicle_type IN ('utilitario', 'vuc', 'toco', 'truck', 'bitruck', 'cavalo_mecanico'))");
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_payload_kg_positive CHECK (payload_kg > 0)');
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_min_meters_per_liter_range CHECK (min_meters_per_liter IS NULL OR min_meters_per_liter BETWEEN 500 AND 30000)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_min_meters_per_liter_range');
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_payload_kg_positive');
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_vehicle_type_valid');
        DB::statement('ALTER TABLE vehicles DROP COLUMN model');
        DB::statement('ALTER TABLE vehicles DROP COLUMN min_meters_per_liter');
        DB::statement("UPDATE vehicles SET vehicle_type = 'executivo', payload_kg = LEAST(payload_kg, 46)");
        DB::statement('ALTER TABLE vehicles ALTER COLUMN payload_kg TYPE smallint');
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_capacity_positive CHECK (payload_kg > 0)');
        DB::statement('ALTER TABLE vehicles RENAME COLUMN payload_kg TO capacity');
        DB::statement('ALTER TABLE vehicles RENAME COLUMN vehicle_type TO class_type');
    }
};
