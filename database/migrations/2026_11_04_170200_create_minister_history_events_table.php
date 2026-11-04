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
        Schema::create('minister_history_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minister_id')
                ->constrained('ministers')
                ->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('code', 20);
            $table->string('description');
            $table->timestamps();

            $table->index(['minister_id', 'code']);
            $table->index(['minister_id', 'occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minister_history_events');
    }
};
