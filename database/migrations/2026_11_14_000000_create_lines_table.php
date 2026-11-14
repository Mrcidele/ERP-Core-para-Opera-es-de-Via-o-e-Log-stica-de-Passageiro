<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('origin', 120);
            $table->string('destination', 120);
            $table->unsignedInteger('distance_km');
            $table->unsignedInteger('estimated_duration_minutes');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lines');
    }
};
