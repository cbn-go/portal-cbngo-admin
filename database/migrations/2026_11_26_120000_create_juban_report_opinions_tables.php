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
        Schema::create('juban_report_opinions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('juban_reports')->cascadeOnDelete();
            $table->string('result', 30);
            $table->string('status', 20)->default('draft');
            $table->date('meeting_date');
            $table->string('meeting_place', 255)->default('sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)');
            $table->string('city', 100)->default('Goiânia (GO)');
            $table->date('issued_on');
            $table->text('reservations')->nullable();
            $table->foreignId('drafted_by')->constrained('users');
            $table->timestamp('issued_at')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['report_id', 'status']);
        });

        Schema::create('juban_report_opinion_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opinion_id')->constrained('juban_report_opinions')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('fiscal_council_members')->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['opinion_id', 'member_id'], 'op_members_opinion_member_unique');
            $table->unique(['opinion_id', 'position'], 'op_members_opinion_position_unique');
        });

        Schema::create('juban_report_opinion_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opinion_id')->constrained('juban_report_opinions')->cascadeOnDelete();
            $table->foreignId('cancelled_by')->constrained('users');
            $table->string('pdf_path', 255);
            $table->char('pdf_sha256', 64);
            $table->text('reason')->nullable();
            $table->timestamp('cancelled_at');
            $table->timestamps();
        });

        Schema::create('juban_report_opinion_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opinion_id')->constrained('juban_report_opinions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juban_report_opinion_comments');
        Schema::dropIfExists('juban_report_opinion_histories');
        Schema::dropIfExists('juban_report_opinion_members');
        Schema::dropIfExists('juban_report_opinions');
    }
};
