<?php

namespace Tests\Feature\Juban;

use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanCategoryResource;
use App\Filament\Juban\Resources\JubanTransactionResource;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JubanPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('juban'));
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function authorizedRoles(): array
    {
        return [
            'super admin' => [UserRole::SUPER_ADMIN],
            'tesouraria' => [UserRole::JUBAN_TREASURER],
            'secretaria' => [UserRole::CONVENTION_EDITOR],
        ];
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function unauthorizedRoles(): array
    {
        return [
            'ministro' => [UserRole::MINISTER],
            'autor' => [UserRole::AUTHOR],
            'representante' => [UserRole::CHURCH_REPRESENTATIVE],
        ];
    }

    #[DataProvider('authorizedRoles')]
    public function test_treasury_personas_open_the_juban_panel_and_resources(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $transaction = JubanTransaction::factory()->receita()->create();

        $this->actingAs($user);

        $this->get('/juban')->assertSuccessful();
        $this->get(JubanTransactionResource::getUrl('index'))->assertSuccessful();
        $this->get(JubanTransactionResource::getUrl('create'))->assertSuccessful();
        $this->get(JubanTransactionResource::getUrl('edit', ['record' => $transaction]))->assertSuccessful();
        $this->get(JubanCategoryResource::getUrl('index'))->assertSuccessful();
        $this->get(JubanCategoryResource::getUrl('create'))->assertSuccessful();
        $this->get(JubanCategoryResource::getUrl('edit', ['record' => $transaction->category]))->assertSuccessful();
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_are_forbidden_in_the_juban_panel(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($user);

        $this->get('/juban')->assertForbidden();
        $this->get('/juban/lancamentos')->assertForbidden();
        $this->get('/juban/categorias')->assertForbidden();
    }

    public function test_inactive_treasurer_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => false]);

        $this->actingAs($user)->get('/juban/lancamentos')->assertForbidden();
    }

    public function test_president_has_read_only_access_to_the_juban_panel_and_resources(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);
        $transaction = JubanTransaction::factory()->receita()->create();

        $this->actingAs($user);

        $this->get('/juban')->assertSuccessful();
        $this->get(JubanTransactionResource::getUrl('index'))->assertSuccessful();
        $this->get(JubanCategoryResource::getUrl('index'))->assertSuccessful();

        $this->get(JubanTransactionResource::getUrl('create'))->assertForbidden();
        $this->get(JubanTransactionResource::getUrl('edit', ['record' => $transaction]))->assertForbidden();
        $this->get(JubanCategoryResource::getUrl('create'))->assertForbidden();
        $this->get(JubanCategoryResource::getUrl('edit', ['record' => $transaction->category]))->assertForbidden();
    }

    public function test_inactive_president_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => false]);

        $this->actingAs($user)->get('/juban/lancamentos')->assertForbidden();
    }

    public function test_guest_is_redirected_to_the_juban_login(): void
    {
        $this->get('/juban')->assertRedirect('/juban/login');
    }

    public function test_treasurer_lands_on_the_juban_panel_and_stays_out_of_admin(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $this->assertStringEndsWith('/juban', $treasurer->getDefaultPanelUrl());
        $this->assertSame('juban', $treasurer->panelForPasswordReset()?->getId());

        $this->actingAs($treasurer)->get('/admin')->assertForbidden();
    }

    public function test_president_lands_on_the_juban_panel_and_stays_out_of_admin(): void
    {
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        $this->assertStringEndsWith('/juban', $president->getDefaultPanelUrl());
        $this->assertSame('juban', $president->panelForPasswordReset()?->getId());

        $this->actingAs($president)->get('/admin')->assertForbidden();
    }

    public function test_secretary_keeps_admin_as_default_panel(): void
    {
        $secretary = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);

        $this->assertStringEndsWith('/admin', $secretary->getDefaultPanelUrl());
        $this->assertTrue($secretary->canAccessPanel(Filament::getPanel('juban')));
    }

    public function test_category_list_shows_categories_to_the_treasurer(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        JubanCategory::factory()->despesa()->create(['name' => 'Eventos e congressos']);

        $this->actingAs($treasurer)
            ->get(JubanCategoryResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Eventos e congressos');
    }
}
