<?php

namespace Tests\Unit\Models;

use App\Enums\JubanCategoryKind;
use App\Enums\JubanPaymentMethod;
use App\Enums\JubanTransactionType;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class JubanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_juban_category_kind_enum(): void
    {
        $this->assertSame('receita', JubanCategoryKind::RECEITA->value);
        $this->assertSame('despesa', JubanCategoryKind::DESPESA->value);
        $this->assertSame('ambos', JubanCategoryKind::AMBOS->value);

        $this->assertTrue(JubanCategoryKind::RECEITA->allows(JubanTransactionType::RECEITA));
        $this->assertFalse(JubanCategoryKind::RECEITA->allows(JubanTransactionType::DESPESA));

        $this->assertTrue(JubanCategoryKind::DESPESA->allows(JubanTransactionType::DESPESA));
        $this->assertFalse(JubanCategoryKind::DESPESA->allows(JubanTransactionType::RECEITA));

        $this->assertTrue(JubanCategoryKind::AMBOS->allows(JubanTransactionType::RECEITA));
        $this->assertTrue(JubanCategoryKind::AMBOS->allows(JubanTransactionType::DESPESA));
    }

    public function test_juban_transaction_type_enum(): void
    {
        $receita = JubanTransactionType::RECEITA;
        $this->assertTrue($receita->isReceita());
        $this->assertFalse($receita->isDespesa());
        $this->assertSame('Receita', $receita->getLabel());

        $despesa = JubanTransactionType::DESPESA;
        $this->assertTrue($despesa->isDespesa());
        $this->assertFalse($despesa->isReceita());
        $this->assertSame('Despesa', $despesa->getLabel());
    }

    public function test_juban_payment_method_enum(): void
    {
        $this->assertSame('PIX', JubanPaymentMethod::PIX->getLabel());
        $this->assertSame('Boleto Bancário', JubanPaymentMethod::BOLETO->getLabel());
        $this->assertSame('Dinheiro (Espécie)', JubanPaymentMethod::DINHEIRO->getLabel());
    }

    public function test_category_model_relations_and_scopes(): void
    {
        $catReceita = JubanCategory::factory()->create([
            'kind' => JubanCategoryKind::RECEITA,
            'is_active' => true,
        ]);

        $catDespesa = JubanCategory::factory()->create([
            'kind' => JubanCategoryKind::DESPESA,
            'is_active' => true,
        ]);

        $catAmbos = JubanCategory::factory()->create([
            'kind' => JubanCategoryKind::AMBOS,
            'is_active' => true,
        ]);

        $catInativa = JubanCategory::factory()->create([
            'kind' => JubanCategoryKind::RECEITA,
            'is_active' => false,
        ]);

        $active = JubanCategory::active()->get();
        $this->assertTrue($active->contains($catReceita));
        $this->assertFalse($active->contains($catInativa));

        $forReceita = JubanCategory::forType(JubanTransactionType::RECEITA)->get();
        $this->assertTrue($forReceita->contains($catReceita));
        $this->assertTrue($forReceita->contains($catAmbos));
        $this->assertFalse($forReceita->contains($catDespesa));

        $forDespesa = JubanCategory::forType(JubanTransactionType::DESPESA)->get();
        $this->assertTrue($forDespesa->contains($catDespesa));
        $this->assertTrue($forDespesa->contains($catAmbos));
        $this->assertFalse($forDespesa->contains($catReceita));

        $this->assertTrue($catReceita->canAcceptType(JubanTransactionType::RECEITA));
        $this->assertFalse($catReceita->canAcceptType(JubanTransactionType::DESPESA));
    }

    public function test_transaction_signed_amount_and_scopes(): void
    {
        $cat = JubanCategory::factory()->create(['kind' => JubanCategoryKind::AMBOS]);

        $receita = JubanTransaction::factory()->create([
            'category_id' => $cat->id,
            'type' => JubanTransactionType::RECEITA,
            'amount' => 500.00,
            'occurred_on' => '2026-11-05',
        ]);

        $despesa = JubanTransaction::factory()->create([
            'category_id' => $cat->id,
            'type' => JubanTransactionType::DESPESA,
            'amount' => 150.00,
            'occurred_on' => '2026-11-10',
        ]);

        $this->assertSame(500.00, $receita->signedAmount());
        $this->assertSame(-150.00, $despesa->signedAmount());

        $this->assertTrue(JubanTransaction::receitas()->whereKey($receita->id)->exists());
        $this->assertFalse(JubanTransaction::receitas()->whereKey($despesa->id)->exists());

        $this->assertTrue(JubanTransaction::despesas()->whereKey($despesa->id)->exists());
        $this->assertFalse(JubanTransaction::despesas()->whereKey($receita->id)->exists());

        $monthResults = JubanTransaction::forMonth(2026, 11)->get();
        $this->assertCount(2, $monthResults);

        $this->assertTrue(JubanTransaction::period('2026-11-01', '2026-11-07')->whereKey($receita->id)->exists());
        $this->assertFalse(JubanTransaction::period('2026-11-01', '2026-11-07')->whereKey($despesa->id)->exists());
    }

    public function test_transaction_auto_populates_creator_and_updater_when_authenticated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $transaction = JubanTransaction::factory()->create([
            'created_by' => null,
            'updated_by' => null,
        ]);

        $this->assertSame($user->id, $transaction->created_by);
        $this->assertSame($user->id, $transaction->updated_by);
        $this->assertSame($user->id, $transaction->creator?->id);
        $this->assertSame($user->id, $transaction->updater?->id);
    }

    public function test_transaction_rejects_non_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JubanTransaction::factory()->create([
            'amount' => 0.00,
        ]);
    }
}
