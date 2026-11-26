<?php

namespace Tests\Feature\Juban;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JubanReportFlowMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_moves_assinado_to_aguardando_conselho_fiscal_and_can_rollback(): void
    {
        $migrationPath = 'database/migrations/2026_11_26_101000_migrate_juban_reports_assinado_to_aguardando_conselho_fiscal.php';

        // Reverte a migração de dados para testar sua execução
        Artisan::call('migrate:rollback', ['--path' => $migrationPath]);

        // Cria registro inicial com status assinado
        $id = DB::table('juban_reports')->insertGetId([
            'year' => 2025,
            'quarter' => 4,
            'version' => 1,
            'period_from' => '2025-10-01',
            'period_until' => '2025-12-31',
            'status' => 'assinado',
            'snapshot' => json_encode(['test' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Executa a migração de dados de transição
        Artisan::call('migrate', ['--path' => $migrationPath]);

        // Status deve ser aguardando_conselho_fiscal
        $statusAfter = DB::table('juban_reports')->where('id', $id)->value('status');
        $this->assertEquals('aguardando_conselho_fiscal', $statusAfter);

        // Rollback da migração de dados
        Artisan::call('migrate:rollback', ['--path' => $migrationPath]);

        $statusRolledBack = DB::table('juban_reports')->where('id', $id)->value('status');
        $this->assertEquals('assinado', $statusRolledBack);
    }
}
