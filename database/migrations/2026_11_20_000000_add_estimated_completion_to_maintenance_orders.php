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
        Schema::table('maintenance_orders', function (Blueprint $table): void {
            // Previsão de o veículo sair da oficina; opcional.
            $table->timestampTz('estimated_completion_at')->nullable()->after('opened_at');
        });

        DB::statement('ALTER TABLE maintenance_orders ADD CONSTRAINT maintenance_orders_estimate_after_opening CHECK (estimated_completion_at IS NULL OR estimated_completion_at > opened_at)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE maintenance_orders DROP CONSTRAINT IF EXISTS maintenance_orders_estimate_after_opening');

        Schema::table('maintenance_orders', function (Blueprint $table): void {
            $table->dropColumn('estimated_completion_at');
        });
    }
};
