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
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('plate', 7);
            $table->string('chassis', 17);
            $table->unsignedSmallInteger('capacity');
            $table->string('class_type', 20);
            $table->string('status', 20)->index();
            $table->unsignedInteger('current_mileage')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Unicidade só entre veículos não excluídos: o histórico é mantido e a placa pode ser recadastrada.
        DB::statement('CREATE UNIQUE INDEX vehicles_plate_unique ON vehicles (plate) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX vehicles_chassis_unique ON vehicles (chassis) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
