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
        Schema::create('maintenance_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('mechanic_id')->constrained('employees')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->text('description');
            $table->integer('cost_cents')->default(0);
            $table->timestampTz('opened_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'status']);
        });

        DB::statement('ALTER TABLE maintenance_orders ADD CONSTRAINT maintenance_orders_cost_not_negative CHECK (cost_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_orders');
    }
};
