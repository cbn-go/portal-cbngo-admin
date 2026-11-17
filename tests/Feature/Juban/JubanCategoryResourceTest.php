<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanCategoryKind;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanCategoryResource\Pages\CreateJubanCategory;
use App\Filament\Juban\Resources\JubanCategoryResource\Pages\EditJubanCategory;
use App\Filament\Juban\Resources\JubanCategoryResource\Pages\ListJubanCategories;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JubanCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('juban'));

        $this->actingAs(User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]));
    }

    public function test_treasurer_creates_a_category(): void
    {
        Livewire::test(CreateJubanCategory::class)
            ->fillForm([
                'name' => 'Viagens e deslocamento',
                'code' => 'viagens-deslocamento',
                'kind' => JubanCategoryKind::DESPESA->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('juban_categories', [
            'name' => 'Viagens e deslocamento',
            'code' => 'viagens-deslocamento',
            'kind' => JubanCategoryKind::DESPESA->value,
            'is_active' => true,
        ]);
    }

    public function test_code_is_generated_from_name_on_create(): void
    {
        Livewire::test(CreateJubanCategory::class)
            ->fillForm(['name' => 'Ação social'])
            ->assertFormSet(['code' => 'acao-social']);
    }

    public function test_code_must_be_unique_and_kind_required(): void
    {
        JubanCategory::factory()->create(['code' => 'eventos-congressos']);

        Livewire::test(CreateJubanCategory::class)
            ->fillForm([
                'name' => 'Eventos e congressos',
                'code' => 'eventos-congressos',
                'kind' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique', 'kind' => 'required']);
    }

    public function test_treasurer_deactivates_a_category(): void
    {
        $category = JubanCategory::factory()->receita()->create();

        Livewire::test(EditJubanCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($category->fresh()?->is_active);
    }

    public function test_list_filters_by_kind_and_status(): void
    {
        $receita = JubanCategory::factory()->receita()->create();
        $despesaInativa = JubanCategory::factory()->despesa()->inactive()->create();

        Livewire::test(ListJubanCategories::class)
            ->filterTable('kind', JubanCategoryKind::RECEITA->value)
            ->assertCanSeeTableRecords([$receita])
            ->assertCanNotSeeTableRecords([$despesaInativa]);

        Livewire::test(ListJubanCategories::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$despesaInativa])
            ->assertCanNotSeeTableRecords([$receita]);
    }

    public function test_category_with_transactions_cannot_be_deleted(): void
    {
        $used = JubanCategory::factory()->despesa()->create();
        JubanTransaction::factory()->despesa()->create(['category_id' => $used->id]);
        $empty = JubanCategory::factory()->despesa()->create();

        Livewire::test(ListJubanCategories::class)
            ->assertTableActionHidden(DeleteAction::class, $used)
            ->callTableAction(DeleteAction::class, $empty);

        $this->assertModelMissing($empty);
        $this->assertModelExists($used);
    }
}
