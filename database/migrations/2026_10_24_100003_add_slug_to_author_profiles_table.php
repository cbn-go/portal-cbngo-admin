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
        Schema::table('author_profiles', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->after('user_id');
        });

        $used = [];

        DB::table('author_profiles')
            ->join('users', 'users.id', '=', 'author_profiles.user_id')
            ->select(['author_profiles.id', 'users.name'])
            ->orderBy('author_profiles.id')
            ->each(function (object $profile) use (&$used): void {
                $base = Str::slug((string) $profile->name) ?: 'autor';
                $slug = $base;
                $count = 1;

                while (isset($used[$slug])) {
                    $slug = "{$base}-{$count}";
                    $count++;
                }

                $used[$slug] = true;

                DB::table('author_profiles')->where('id', $profile->id)->update(['slug' => $slug]);
            });

        Schema::table('author_profiles', function (Blueprint $table) {
            $table->string('slug', 255)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('author_profiles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
