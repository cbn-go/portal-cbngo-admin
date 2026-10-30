<?php

use App\Models\Church;
use App\Models\Municipality;
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
        Schema::table('churches', function (Blueprint $table): void {
            $table->foreignId('municipality_id')
                ->nullable()
                ->after('pastor_name')
                ->constrained('municipalities')
                ->nullOnDelete();
        });

        // Casamento retroativo de igrejas preexistentes se a base de municípios já estiver populada
        if (Schema::hasTable('municipalities') && Municipality::query()->exists()) {
            foreach (Church::query()->whereNull('municipality_id')->whereNotNull('city')->cursor() as $church) {
                $municipality = Municipality::findByNormalizedName($church->city);
                if ($municipality) {
                    $church->updateQuietly([
                        'municipality_id' => $municipality->id,
                        'city' => $municipality->name,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropForeign(['municipality_id']);
            $table->dropColumn('municipality_id');
        });
    }
};
