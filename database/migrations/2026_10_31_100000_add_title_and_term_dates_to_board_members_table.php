<?php

use App\Enums\AmigoRegion;
use App\Enums\ClergyTitle;
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
        Schema::table('board_members', function (Blueprint $table) {
            $table->string('title', 20)->nullable()->after('name');
            $table->date('term_starts_on')->nullable()->after('term_label');
            $table->date('term_ends_on')->nullable()->after('term_starts_on');

            $table->index(['term_starts_on', 'term_ends_on']);
        });

        // Dados existentes: o título sai do início do nome e a região em texto
        // livre vira uma das quatro AMIGOs (o que não for reconhecido fica vazio).
        DB::table('board_members')->orderBy('id')->each(function (object $member): void {
            [$title, $name] = ClergyTitle::splitName($member->name);
            $region = $member->region === null ? null : AmigoRegion::fromText($member->region)?->value;

            DB::table('board_members')->where('id', $member->id)->update([
                'title' => $title?->value,
                'name' => $name,
                'region' => $region,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('board_members')->whereNotNull('title')->orderBy('id')->each(function (object $member): void {
            DB::table('board_members')->where('id', $member->id)->update([
                'name' => $member->title.' '.$member->name,
            ]);
        });

        Schema::table('board_members', function (Blueprint $table) {
            $table->dropIndex(['term_starts_on', 'term_ends_on']);
            $table->dropColumn(['title', 'term_starts_on', 'term_ends_on']);
        });
    }
};
