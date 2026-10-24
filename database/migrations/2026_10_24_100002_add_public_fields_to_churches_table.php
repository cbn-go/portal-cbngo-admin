<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->after('name');
            $table->decimal('latitude', 10, 7)->nullable()->after('complement');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('service_times')->nullable()->after('social_links');

            $table->index(['is_active', 'city']);
        });

        $used = [];

        DB::table('churches')->select(['id', 'name'])->orderBy('id')->each(function (object $church) use (&$used): void {
            $base = Str::slug((string) $church->name) ?: 'igreja';
            $slug = $base;
            $count = 1;

            while (isset($used[$slug])) {
                $slug = "{$base}-{$count}";
                $count++;
            }

            $used[$slug] = true;

            DB::table('churches')->where('id', $church->id)->update(['slug' => $slug]);
        });

        Schema::table('churches', function (Blueprint $table) {
            $table->string('slug', 255)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['is_active', 'city']);
            $table->dropColumn(['slug', 'latitude', 'longitude', 'service_times']);
        });
    }
};
