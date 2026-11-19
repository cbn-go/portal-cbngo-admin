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
        Schema::create('juban_report_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('juban_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20);
            $table->foreignId('passkey_id')->constrained('passkeys')->restrictOnDelete();
            $table->string('nonce', 64);
            $table->string('challenge', 64);
            $table->mediumText('client_data_json');
            $table->text('authenticator_data');
            $table->text('signature');
            $table->char('pdf_sha256', 64);
            $table->timestamp('signed_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'role'], 'uq_juban_report_signatures_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juban_report_signatures');
    }
};
