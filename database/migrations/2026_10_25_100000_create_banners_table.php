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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->string('image');
            $table->string('focal_point', 10)->default('center');
            $table->string('alt_text', 255);
            $table->string('link_url', 255)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->boolean('opens_in_new_tab')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
