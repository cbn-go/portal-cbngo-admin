<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanCategoryKind;
use App\Enums\JubanTransactionType;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use Database\Seeders\JubanCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JubanSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_juban_tables_exist_with_proper_columns(): void
    {
        $this->assertTrue(Schema::hasTable('juban_categories'));
        $this->assertTrue(Schema::hasColumns('juban_categories', [
            'id',
            'name',
            'code',
            'kind',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('juban_transactions'));
        $this->assertTrue(Schema::hasColumns('juban_transactions', [
            'id',
            'type',
            'amount',
            'occurred_on',
            'category_id',
            'description',
            'payment_method',
            'document_ref',
            'notes',
            'created_by',
            'updated_by',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_category_deletion_is_restricted_when_transactions_exist(): void
    {
        $category = JubanCategory::factory()->create();

        JubanTransaction::factory()->create([
            'category_id' => $category->id,
            'amount' => 150.00,
            'type' => JubanTransactionType::RECEITA,
        ]);

        $this->expectException(QueryException::class);
        $category->delete();
    }

    public function test_juban_category_seeder_is_idempotent_and_creates_default_categories(): void
    {
        $this->seed(JubanCategorySeeder::class);

        $initialCount = JubanCategory::count();
        $this->assertSame(14, $initialCount);

        $this->assertDatabaseHas('juban_categories', [
            'code' => 'inscricoes-eventos',
            'kind' => JubanCategoryKind::RECEITA->value,
        ]);

        $this->assertDatabaseHas('juban_categories', [
            'code' => 'alimentacao',
            'kind' => JubanCategoryKind::DESPESA->value,
        ]);

        $this->assertDatabaseHas('juban_categories', [
            'code' => 'ajuste-saldo',
            'kind' => JubanCategoryKind::AMBOS->value,
        ]);

        // Executa novamente para assegurar idempotência
        $this->seed(JubanCategorySeeder::class);
        $this->assertSame($initialCount, JubanCategory::count());
    }

    public function test_soft_deleted_transaction_is_excluded_from_default_query_but_accessible_via_with_trashed(): void
    {
        $transaction = JubanTransaction::factory()->create([
            'description' => 'Transação para cancelar',
            'amount' => 200.00,
        ]);

        $transaction->delete();

        $this->assertSoftDeleted('juban_transactions', ['id' => $transaction->id]);
        $this->assertNull(JubanTransaction::find($transaction->id));
        $this->assertNotNull(JubanTransaction::withTrashed()->find($transaction->id));
    }
}
