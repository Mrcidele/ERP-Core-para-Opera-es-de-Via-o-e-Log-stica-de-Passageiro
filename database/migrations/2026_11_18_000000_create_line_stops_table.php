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
        Schema::create('line_stops', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('line_id')->constrained('lines')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('city_name', 120);
            $table->unsignedInteger('cumulative_km');
            $table->unsignedInteger('estimated_minutes');
            $table->timestamps();

            // Também serve de índice para buscar as paradas de uma linha em ordem.
            $table->unique(['line_id', 'sequence']);
        });

        DB::statement('ALTER TABLE line_stops ADD CONSTRAINT line_stops_sequence_positive CHECK (sequence > 0)');
        DB::statement('ALTER TABLE line_stops ADD CONSTRAINT line_stops_km_not_negative CHECK (cumulative_km >= 0)');
        DB::statement('ALTER TABLE line_stops ADD CONSTRAINT line_stops_minutes_not_negative CHECK (estimated_minutes >= 0)');

        // Exclusão de linha consulta as viagens agendadas ou em andamento dela.
        DB::statement("CREATE INDEX trips_active_by_line ON trips (line_id) WHERE status IN ('scheduled', 'in_progress')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS trips_active_by_line');
        Schema::dropIfExists('line_stops');
    }
};
