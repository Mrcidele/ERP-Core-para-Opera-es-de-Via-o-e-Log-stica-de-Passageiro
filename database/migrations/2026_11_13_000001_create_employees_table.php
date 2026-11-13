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
        Schema::create('employees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('cpf', 11);
            $table->string('role', 20)->index();
            $table->string('cnh_number', 11)->nullable();
            $table->string('cnh_category', 2)->nullable();
            $table->date('cnh_expiration_date')->nullable();
            $table->string('status', 20)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Unicidade só entre funcionários não excluídos (índice parcial, PostgreSQL/SQLite).
        DB::statement('CREATE UNIQUE INDEX employees_cpf_unique ON employees (cpf) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX employees_cnh_number_unique ON employees (cnh_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
