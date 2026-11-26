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
        Schema::table('juban_reports', function (Blueprint $table) {
            $table->unsignedSmallInteger('version')->default(1)->after('quarter');
            $table->dropUnique(['year', 'quarter']);
            $table->unique(['year', 'quarter', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('juban_reports', function (Blueprint $table) {
            $table->dropUnique(['year', 'quarter', 'version']);
            $table->unique(['year', 'quarter']);
            $table->dropColumn('version');
        });
    }
};
