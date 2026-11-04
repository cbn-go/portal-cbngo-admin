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
        Schema::create('minister_family_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minister_id')
                ->constrained('ministers')
                ->cascadeOnDelete();
            $table->string('relationship', 50);
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->timestamps();

            $table->index(['minister_id', 'relationship']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minister_family_members');
    }
};
