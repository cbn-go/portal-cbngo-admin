<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Minister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MinisterHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ministerUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Fulano Ciclano de Tal',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ], $attributes));
    }

    private function ordainedMinister(User $user, ?Church $church, array $attributes = []): Minister
    {
        $churchAttributes = $church === null ? [] : ['church_id' => $church->id, 'church_name' => $church->name];

        $minister = Minister::factory()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Fulano Ciclano de Tal',
            'registration_number' => 'ROL-4242',
            'status' => Minister::STATUS_ACTIVE,
            'function' => 'Pastor',
        ], $churchAttributes, $attributes));

        $minister->historyEvents()->create([
            'occurred_on' => '2014-03-10',
            'code' => '2',
            'description' => 'Ordenação',
        ]);

        return $minister;
    }

    public function test_minister_with_record_and_church_sees_own_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 15:00', 'America/Sao_Paulo'));

        $church = Church::factory()->create(['name' => 'Igreja Batista Nacional Central', 'is_active' => true]);
        $user = $this->ministerUser(['church_id' => $church->id]);
        $this->ordainedMinister($user, $church);

        $this->actingAs($user)->get('/ormiban')
            ->assertSuccessful()
            ->assertSee('Boa tarde, Fulano')
            ->assertSee('Sexta-feira, 06 de novembro de 2026')
            ->assertSee('Fulano Ciclano de Tal')
            ->assertSee('Pastor')
            ->assertSee('ROL-4242')
            ->assertSee(Minister::STATUS_ACTIVE)
            ->assertSee('Igreja Batista Nacional Central')
            ->assertSee('12 anos (desde a ordenação)')
            ->assertDontSee('data-testid="church-invite"', false)
            ->assertDontSee('Nenhuma ficha ministerial vinculada');
    }

    public function test_greeting_and_date_use_sao_paulo_timezone(): void
    {
        // 01:30 UTC de sábado ainda é 22:30 de sexta em Brasília.
        Carbon::setTestNow(Carbon::parse('2026-11-07 01:30', 'UTC'));

        $user = $this->ministerUser();
        $this->ordainedMinister($user, null);

        $this->actingAs($user)->get('/ormiban')
            ->assertSuccessful()
            ->assertSee('Boa noite, Fulano')
            ->assertSee('Sexta-feira, 06 de novembro de 2026');
    }

    public function test_summary_uses_same_ministry_time_as_profile(): void
    {
        $user = $this->ministerUser();
        $this->ordainedMinister($user, null)->historyEvents()->where('code', '2')->delete();

        $this->actingAs($user)->get('/ormiban')
            ->assertSuccessful()
            ->assertSee('Sem registro de ordenação');
    }

    public function test_minister_without_church_sees_invite_to_link_or_register(): void
    {
        $user = $this->ministerUser();
        // A igreja informada no rol (texto) não é vínculo: o convite continua aparecendo.
        $this->ordainedMinister($user, null, ['church_name' => 'IBN do Setor Sul']);

        $this->actingAs($user)->get('/ormiban')
            ->assertSuccessful()
            ->assertSee('IBN do Setor Sul')
            ->assertSee('data-testid="church-invite"', false)
            ->assertSee('Vincular ou cadastrar igreja')
            ->assertSee('href="'.url('/ormiban/minha-igreja').'" class="oh-btn"', false);
    }

    public function test_user_without_minister_record_sees_secretariat_notice(): void
    {
        $user = $this->ministerUser();

        $this->actingAs($user)->get('/ormiban')
            ->assertSuccessful()
            ->assertSee('Nenhuma ficha ministerial vinculada')
            ->assertSee('entre em contato com a secretaria da CBN-GO para associar sua matrícula oficial')
            ->assertDontSee('Minha ficha no rol')
            ->assertDontSee('data-testid="church-invite"', false);
    }

    public function test_profile_shows_the_same_secretariat_notice(): void
    {
        $user = $this->ministerUser();

        $this->actingAs($user)->get('/ormiban/profile')
            ->assertSuccessful()
            ->assertSee('Nenhuma ficha ministerial vinculada')
            ->assertSee('entre em contato com a secretaria da CBN-GO para associar sua matrícula oficial');
    }

    public function test_super_admin_without_record_sees_minister_screen_notice(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Geral',
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $this->actingAs($admin)->get('/ormiban')
            ->assertSuccessful()
            ->assertSee('data-testid="super-admin-notice"', false)
            ->assertSee('Esta é a tela inicial do ministro.')
            ->assertDontSee('Minha ficha no rol')
            ->assertDontSee('Nenhuma ficha ministerial vinculada');
    }

    public function test_each_shortcut_opens_an_existing_page(): void
    {
        $church = Church::factory()->create(['is_active' => true]);
        $user = $this->ministerUser(['church_id' => $church->id]);
        $this->ordainedMinister($user, $church);

        $response = $this->actingAs($user)->get('/ormiban')->assertSuccessful();

        $shortcuts = [
            'Meu Perfil' => '/ormiban/profile',
            'Minha Igreja' => '/ormiban/minha-igreja',
            'Projetos Sociais' => '/ormiban/social-projects',
            'Visão de Goiás' => '/ormiban/estrategia',
        ];

        foreach ($shortcuts as $label => $path) {
            $response->assertSee($label);
            $response->assertSee('href="'.url($path).'"', false);
            $this->actingAs($user)->get($path)->assertSuccessful();
        }
    }

    public function test_home_does_not_show_convention_totals_review_queue_or_money(): void
    {
        $church = Church::factory()->create(['is_active' => true]);
        $user = $this->ministerUser(['church_id' => $church->id]);
        $this->ordainedMinister($user, $church);

        $this->actingAs($user)->get('/ormiban')
            ->assertSuccessful()
            ->assertDontSee('Igrejas ativas')
            ->assertDontSee('Pastores no rol')
            ->assertDontSee('Aguardando revisão')
            ->assertDontSee('Contribuições')
            ->assertDontSee('R$');
    }
}
