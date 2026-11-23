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
        Schema::table('juban_transactions', function (Blueprint $table) {
            $table->string('receipt_path', 500)->nullable()->after('document_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('juban_transactions', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
