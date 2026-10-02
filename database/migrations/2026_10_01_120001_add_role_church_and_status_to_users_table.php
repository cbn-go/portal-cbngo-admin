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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('church_representative')->after('password')->index();
            $table->foreignId('church_id')->nullable()->after('role')->index()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('church_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('church_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
