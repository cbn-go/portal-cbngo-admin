<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('juban_reports')
            ->where('status', 'assinado')
            ->update(['status' => 'aguardando_conselho_fiscal']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('juban_reports')
            ->where('status', 'aguardando_conselho_fiscal')
            ->update(['status' => 'assinado']);
    }
};
