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
        Schema::table('churches', function (Blueprint $table) {
            $table->string('type', 20)->default('igreja')->after('slug');
            $table->foreignId('parent_church_id')
                ->nullable()
                ->after('type')
                ->constrained('churches')
                ->nullOnDelete();
            $table->foreignId('leader_minister_id')
                ->nullable()
                ->after('pastor_name')
                ->constrained('ministers')
                ->nullOnDelete();

            $table->index(['type', 'parent_church_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropForeign(['parent_church_id']);
            $table->dropForeign(['leader_minister_id']);
            $table->dropIndex(['type', 'parent_church_id']);
            $table->dropColumn(['type', 'parent_church_id', 'leader_minister_id']);
        });
    }
};
