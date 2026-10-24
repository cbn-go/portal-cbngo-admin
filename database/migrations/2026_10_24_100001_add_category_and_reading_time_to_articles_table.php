<?php

use App\Support\ReadingTime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('category', 100)->default('Teologia')->after('slug');
            $table->unsignedSmallInteger('reading_time')->default(1)->after('content');

            $table->index(['category', 'status', 'published_at']);
        });

        DB::table('articles')->select(['id', 'content'])->orderBy('id')->each(function (object $article): void {
            DB::table('articles')
                ->where('id', $article->id)
                ->update(['reading_time' => ReadingTime::fromHtml($article->content)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['category', 'status', 'published_at']);
            $table->dropColumn(['category', 'reading_time']);
        });
    }
};
