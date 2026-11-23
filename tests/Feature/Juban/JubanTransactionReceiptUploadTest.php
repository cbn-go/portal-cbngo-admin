<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanPaymentMethod;
use App\Enums\JubanReportStatus;
use App\Enums\JubanTransactionType;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\CreateJubanTransaction;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\EditJubanTransaction;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\ListJubanTransactions;
use App\Models\JubanCategory;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class JubanTransactionReceiptUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $treasurer;

    private JubanCategory $despesaCategory;

    private JubanCategory $receitaCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('juban'));

        Storage::fake(config('media.disk'));

        $this->treasurer = User::factory()->create([
            'role' => UserRole::JUBAN_TREASURER,
            'is_active' => true,
        ]);

        $this->despesaCategory = JubanCategory::factory()->despesa()->create(['name' => 'Transporte e Viagens']);
        $this->receitaCategory = JubanCategory::factory()->receita()->create(['name' => 'Ofertas']);

        $this->actingAs($this->treasurer);
    }

    public function test_treasurer_can_attach_pdf_receipt_when_creating_expense(): void
    {
        $file = UploadedFile::fake()->create('comprovante_uber.pdf', 500, 'application/pdf');

        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '150.00',
                'occurred_on' => '2026-05-10',
                'category_id' => $this->despesaCategory->id,
                'description' => 'Translado palestrante',
                'payment_method' => JubanPaymentMethod::PIX->value,
                'document_ref' => 'UBER-987',
                'receipt_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transaction = JubanTransaction::query()->sole();

        $this->assertNotNull($transaction->receipt_path);
        $this->assertTrue($transaction->hasReceipt());
        Storage::disk(config('media.disk'))->assertExists($transaction->receipt_path);
    }

    public function test_treasurer_can_attach_image_receipt_when_creating_expense(): void
    {
        $image = UploadedFile::fake()->image('cupom_fiscal.png', 800, 600);

        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '45.00',
                'occurred_on' => '2026-05-11',
                'category_id' => $this->despesaCategory->id,
                'description' => 'Lanche motorista',
                'receipt_path' => $image,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transaction = JubanTransaction::query()->sole();

        $this->assertNotNull($transaction->receipt_path);
        $this->assertTrue(str_contains($transaction->receipt_path, 'juban/receipts'));
        Storage::disk(config('media.disk'))->assertExists($transaction->receipt_path);
    }

    public function test_receipt_field_is_visible_for_expense_and_hidden_for_income(): void
    {
        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
            ])
            ->assertFormFieldIsVisible('receipt_path')
            ->fillForm([
                'type' => JubanTransactionType::RECEITA->value,
                'category_id' => $this->receitaCategory->id,
            ])
            ->assertFormFieldIsHidden('receipt_path');
    }

    public function test_treasurer_can_attach_receipt_on_edit(): void
    {
        $transaction = JubanTransaction::factory()->despesa()->create([
            'category_id' => $this->despesaCategory->id,
            'receipt_path' => null,
            'occurred_on' => '2026-05-12',
        ]);

        $newFile = UploadedFile::fake()->create('novo_recibo.pdf', 300, 'application/pdf');

        Livewire::test(EditJubanTransaction::class, ['record' => $transaction->getRouteKey()])
            ->fillForm([
                'receipt_path' => $newFile,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $transaction->refresh();
        $this->assertNotNull($transaction->receipt_path);
        Storage::disk(config('media.disk'))->assertExists($transaction->receipt_path);
    }

    public function test_treasurer_can_remove_receipt_on_edit(): void
    {
        $oldFile = UploadedFile::fake()->create('antigo.pdf', 200, 'application/pdf');
        $oldPath = $oldFile->store('juban/receipts', config('media.disk'));

        $transaction = JubanTransaction::factory()->despesa()->create([
            'category_id' => $this->despesaCategory->id,
            'receipt_path' => $oldPath,
            'occurred_on' => '2026-05-12',
        ]);

        Livewire::test(EditJubanTransaction::class, ['record' => $transaction->getRouteKey()])
            ->fillForm([
                'receipt_path' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $transaction->refresh();
        $this->assertNull($transaction->receipt_path);
    }

    public function test_table_shows_receipt_column_and_link(): void
    {
        $transactionWithReceipt = JubanTransaction::factory()->despesa()->create([
            'category_id' => $this->despesaCategory->id,
            'receipt_path' => 'juban/receipts/comprovante-aprovado.pdf',
            'occurred_on' => '2026-05-15',
            'description' => 'Despesa com anexo',
        ]);

        $transactionWithoutReceipt = JubanTransaction::factory()->despesa()->create([
            'category_id' => $this->despesaCategory->id,
            'receipt_path' => null,
            'occurred_on' => '2026-05-16',
            'description' => 'Despesa sem anexo',
        ]);

        Livewire::test(ListJubanTransactions::class)
            ->assertCanSeeTableRecords([$transactionWithReceipt, $transactionWithoutReceipt])
            ->assertTableColumnExists('receipt_path');
    }

    public function test_rejects_unaccepted_file_format(): void
    {
        $invalidFile = UploadedFile::fake()->create('executavel.exe', 100, 'application/x-msdownload');

        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '80.00',
                'occurred_on' => '2026-05-10',
                'category_id' => $this->despesaCategory->id,
                'description' => 'Arquivo suspeito',
                'receipt_path' => $invalidFile,
            ])
            ->call('create')
            ->assertHasFormErrors(['receipt_path']);

        $this->assertDatabaseMissing('juban_transactions', [
            'description' => 'Arquivo suspeito',
        ]);
    }

    public function test_cannot_save_transaction_when_period_is_locked_by_quarterly_report(): void
    {
        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::ASSINADO,
        ]);

        $file = UploadedFile::fake()->create('recibo_antigo.pdf', 100, 'application/pdf');

        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '100.00',
                'occurred_on' => '2026-02-15', // Q1 fechado
                'category_id' => $this->despesaCategory->id,
                'description' => 'Tentativa em período fechado',
                'receipt_path' => $file,
            ])
            ->call('create')
            ->assertHasFormErrors(['occurred_on']);
    }
}
