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
        Schema::create('municipality_indicators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')
                ->constrained('municipalities')
                ->cascadeOnDelete();
            $table->string('indicator', 50);
            $table->string('period', 10);
            $table->decimal('value', 14, 2);
            $table->string('source', 100);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['municipality_id', 'indicator', 'period'], 'idx_indicators_unique');
            $table->index(['indicator', 'period'], 'idx_indicators_query');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipality_indicators');
    }
};
