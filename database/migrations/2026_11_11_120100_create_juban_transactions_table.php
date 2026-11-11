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
        Schema::create('juban_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->date('occurred_on');
            $table->foreignId('category_id')->constrained('juban_categories')->restrictOnDelete();
            $table->string('description', 255);
            $table->string('payment_method', 30)->nullable();
            $table->string('document_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['occurred_on', 'type']);
            $table->index(['category_id', 'occurred_on']);
            $table->index(['type', 'occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juban_transactions');
    }
};
