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
        Schema::create('ministers', function (Blueprint $table): void {
            $table->id();
            $table->string('registration_number', 20)->unique();
            $table->string('function', 50);
            $table->string('name');
            $table->string('status', 50)->default('ATIVO(A)');
            $table->string('sectional', 50)->default('ORMIBAN GO');
            $table->string('church_name');
            $table->text('address')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('cpf', 11)->unique();
            $table->date('birth_date')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('education_level', 100)->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('profession', 150)->nullable();
            $table->string('identity_document', 50)->nullable();
            $table->string('identity_issuer', 50)->nullable();
            $table->string('passport', 50)->nullable();
            $table->date('identity_issued_at')->nullable();
            $table->json('other_phones')->nullable();
            $table->json('occupational_roles')->nullable();
            $table->dateTime('registered_at')->nullable();
            $table->dateTime('source_updated_at')->nullable();

            $table->foreignId('church_id')
                ->nullable()
                ->constrained('churches')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('name');
            $table->index('function');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministers');
    }
};
