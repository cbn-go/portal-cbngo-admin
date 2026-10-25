<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O banner passa a ser a arte completa (com textos embutidos), exibida inteira
 * pelo portal: sem subtítulo escrito pelo site, sem ponto focal de recorte e
 * com espaço para o texto alternativo transcrever a arte.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'focal_point']);
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('alt_text', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('alt_text', 255)->change();
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('subtitle', 255)->nullable()->after('title');
            $table->string('focal_point', 10)->default('center')->after('image');
        });
    }
};
