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
        Schema::create('social_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();

            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('category', 40);
            $table->string('summary', 300);
            $table->longText('description');

            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();

            $table->string('target_audience', 255)->nullable();
            $table->unsignedInteger('beneficiaries_count')->nullable();
            $table->string('city', 120)->index();

            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->text('how_to_help')->nullable();

            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['category', 'status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_projects');
    }
};
