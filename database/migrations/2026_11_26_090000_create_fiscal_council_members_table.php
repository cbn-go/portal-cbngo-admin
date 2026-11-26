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
        Schema::create('fiscal_council_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('seat', 30);
            $table->string('title', 30)->nullable();
            $table->string('qualification', 150)->nullable();
            $table->date('term_starts_on');
            $table->date('term_ends_on');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['seat', 'active']);
            $table->index(['user_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_council_members');
    }
};
