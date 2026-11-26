<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\UserRole;
use App\Filament\Pages\ManagePasskeys;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalCouncilPasskeysTest extends TestCase
{
    use RefreshDatabase;

    private User $fiscalCouncilUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalCouncilUser = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);
    }

    public function test_fiscal_council_member_can_open_manage_passkeys_page_in_fiscal_council_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('conselho-fiscal'));

        $this->actingAs($this->fiscalCouncilUser);

        Livewire::test(ManagePasskeys::class)
            ->assertSuccessful();
    }

    public function test_manage_passkeys_url_is_accessible_in_fiscal_council_panel(): void
    {
        $this->actingAs($this->fiscalCouncilUser)
            ->get('/conselho-fiscal/passkeys')
            ->assertSuccessful();
    }

    public function test_other_roles_cannot_open_manage_passkeys_in_fiscal_council_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('conselho-fiscal'));

        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $this->actingAs($author);

        Livewire::test(ManagePasskeys::class)
            ->assertForbidden();
    }
}
