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
        Schema::table('juban_report_signatures', function (Blueprint $table) {
            $table->foreignId('opinion_id')
                ->nullable()
                ->after('report_id')
                ->constrained('juban_report_opinions')
                ->nullOnDelete();

            $table->text('divergent_vote')
                ->nullable()
                ->after('signature');

            $table->index(['opinion_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('juban_report_signatures', function (Blueprint $table) {
            $table->dropIndex(['opinion_id', 'role']);
            $table->dropForeign(['opinion_id']);
            $table->dropColumn(['opinion_id', 'divergent_vote']);
        });
    }
};
