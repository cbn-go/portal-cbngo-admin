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
        Schema::table('juban_report_opinions', function (Blueprint $table) {
            $table->string('signed_pdf_path', 255)
                ->nullable()
                ->after('pdf_sha256');

            $table->char('signed_pdf_sha256', 64)
                ->nullable()
                ->after('signed_pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('juban_report_opinions', function (Blueprint $table) {
            $table->dropColumn(['signed_pdf_path', 'signed_pdf_sha256']);
        });
    }
};
