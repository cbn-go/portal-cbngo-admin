<?php

namespace Tests\Unit\Services\Juban;

use App\Enums\JubanPaymentMethod;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Services\Juban\JubanTransactionExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JubanTransactionExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private JubanTransactionExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JubanTransactionExportService;
    }

    public function test_headers_returns_all_required_columns(): void
    {
        $expected = [
            'Data',
            'Tipo',
            'Categoria',
            'Descrição',
            'Valor (R$)',
            'Valor Contábil',
            'Forma de Pagamento',
            'Documento / Ref',
            'Lançado por',
        ];

        $this->assertSame($expected, $this->service->headers());
    }

    public function test_map_row_formats_receita_and_despesa_correctly(): void
    {
        $creator = User::factory()->create(['name' => 'Tesoureiro Lucas']);
        $catReceita = JubanCategory::factory()->receita()->create(['name' => 'Inscrições Congresso']);
        $catDespesa = JubanCategory::factory()->despesa()->create(['name' => 'Alimentação & Coffee']);

        $receita = JubanTransaction::factory()->receita()->create([
            'category_id' => $catReceita->id,
            'amount' => '1250.50',
            'occurred_on' => '2026-05-15',
            'description' => 'Lote 1 Congresso; Jovens',
            'payment_method' => JubanPaymentMethod::PIX,
            'document_ref' => 'PIX-123456',
            'created_by' => $creator->id,
        ]);

        $despesa = JubanTransaction::factory()->despesa()->create([
            'category_id' => $catDespesa->id,
            'amount' => '320.00',
            'occurred_on' => '2026-05-18',
            'description' => 'Coffee break tarde',
            'payment_method' => null,
            'document_ref' => null,
            'created_by' => null,
        ]);

        $rowReceita = $this->service->mapRow($receita);
        $this->assertSame('15/05/2026', $rowReceita[0]);
        $this->assertSame('Receita', $rowReceita[1]);
        $this->assertSame('Inscrições Congresso', $rowReceita[2]);
        $this->assertSame('Lote 1 Congresso; Jovens', $rowReceita[3]);
        $this->assertSame('1.250,50', $rowReceita[4]);
        $this->assertSame('1250.50', $rowReceita[5]);
        $this->assertSame('PIX', $rowReceita[6]);
        $this->assertSame('PIX-123456', $rowReceita[7]);
        $this->assertSame('Tesoureiro Lucas', $rowReceita[8]);

        $rowDespesa = $this->service->mapRow($despesa);
        $this->assertSame('18/05/2026', $rowDespesa[0]);
        $this->assertSame('Despesa', $rowDespesa[1]);
        $this->assertSame('Alimentação & Coffee', $rowDespesa[2]);
        $this->assertSame('Coffee break tarde', $rowDespesa[3]);
        $this->assertSame('-320,00', $rowDespesa[4]);
        $this->assertSame('-320.00', $rowDespesa[5]);
        $this->assertSame('', $rowDespesa[6]);
        $this->assertSame('', $rowDespesa[7]);
        $this->assertSame('-', $rowDespesa[8]);
    }

    public function test_generate_csv_content_includes_bom_and_properly_escapes_csv(): void
    {
        $category = JubanCategory::factory()->create(['name' => 'Eventos "Especiais"']);
        $transaction = JubanTransaction::factory()->create([
            'category_id' => $category->id,
            'description' => "Linha com; ponto e vírgula e \"aspas\"\nSegunda linha",
            'amount' => '100.00',
            'occurred_on' => '2026-06-01',
        ]);

        $csv = $this->service->generateCsvContent(JubanTransaction::query());

        // Deve conter o BOM UTF-8
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        // Deve conter o cabeçalho separado por ponto e vírgula
        $this->assertStringContainsString('Data;Tipo;Categoria;Descrição;"Valor (R$)";"Valor Contábil";"Forma de Pagamento";"Documento / Ref";"Lançado por"', $csv);

        // Deve escapar o texto com aspas duplas
        $this->assertStringContainsString('Eventos ""Especiais""', $csv);
        $this->assertStringContainsString('Linha com; ponto e vírgula e ""aspas""', $csv);
    }
}
