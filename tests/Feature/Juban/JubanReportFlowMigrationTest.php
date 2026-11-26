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
        // Reverte a última migração (migração de dados) para testar sua execução
        Artisan::call('migrate:rollback', ['--step' => 1]);

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
        Artisan::call('migrate');

        // Status deve ser aguardando_conselho_fiscal
        $statusAfter = DB::table('juban_reports')->where('id', $id)->value('status');
        $this->assertEquals('aguardando_conselho_fiscal', $statusAfter);

        // Rollback da última migração
        Artisan::call('migrate:rollback', ['--step' => 1]);

        $statusRolledBack = DB::table('juban_reports')->where('id', $id)->value('status');
        $this->assertEquals('assinado', $statusRolledBack);
    }
}
