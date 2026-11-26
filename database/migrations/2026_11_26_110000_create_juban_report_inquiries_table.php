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
        Schema::create('juban_report_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('juban_reports')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('juban_transactions')->nullOnDelete();
            $table->foreignId('asked_by')->constrained('users')->cascadeOnDelete();
            $table->text('question');
            $table->timestamp('asked_at');
            $table->string('status', 30)->default('aberto');
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('closed_without_answer_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_without_answer_at')->nullable();
            $table->text('closed_without_answer_reason')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juban_report_inquiries');
    }
};
