<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('ibge_code', 7)->unique();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('normalized_name', 100)->index();
            $table->string('immediate_region_code', 10)->index();
            $table->string('immediate_region_name', 100);
            $table->string('intermediate_region_code', 10)->index();
            $table->string('intermediate_region_name', 100);
            $table->string('micro_region_name', 100)->nullable();
            $table->string('meso_region_name', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
