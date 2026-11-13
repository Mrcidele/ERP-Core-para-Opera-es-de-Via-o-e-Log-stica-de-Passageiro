<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('plate', 7)->unique();
            $table->string('chassis', 17)->unique();
            $table->unsignedSmallInteger('capacity');
            $table->string('class_type', 20);
            $table->string('status', 20)->index();
            $table->unsignedInteger('current_mileage')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
