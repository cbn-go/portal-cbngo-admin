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
        Schema::create('institutional_settings', function (Blueprint $table) {
            $table->id();

            // Contato
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('office_hours', 150)->nullable();

            // Endereço da Sede
            $table->string('street', 200)->nullable();
            $table->string('number', 30)->nullable();
            $table->string('complement', 150)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->default('Goiânia');
            $table->string('state', 2)->default('GO');
            $table->string('postal_code', 10)->nullable();
            $table->text('maps_url')->nullable();

            // Redes Sociais
            $table->string('instagram_url', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('youtube_url', 255)->nullable();
            $table->string('tiktok_url', 255)->nullable();
            $table->string('spotify_url', 255)->nullable();
            $table->string('linkedin_url', 255)->nullable();

            // Auditoria
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutional_settings');
    }
};
