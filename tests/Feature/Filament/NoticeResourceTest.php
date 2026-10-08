<?php

namespace Tests\Feature\Filament;

use App\Enums\NoticePriority;
use App\Enums\UserRole;
use App\Filament\Resources\NoticeResource;
use App\Filament\Resources\NoticeResource\Pages\CreateNotice;
use App\Filament\Resources\NoticeResource\Pages\EditNotice;
use App\Filament\Resources\NoticeResource\Pages\ListNotices;
use App\Models\Notice;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class NoticeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_convention_editor_can_access_notice_pages(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $conventionEditor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $notice = Notice::factory()->create(['user_id' => $superAdmin->id]);

        $this->actingAs($superAdmin)
            ->get(NoticeResource::getUrl('index'))
            ->assertSuccessful();

        $this->actingAs($superAdmin)
            ->get(NoticeResource::getUrl('create'))
            ->assertSuccessful();

        $this->actingAs($superAdmin)
            ->get(NoticeResource::getUrl('edit', ['record' => $notice]))
            ->assertSuccessful();

        $this->actingAs($conventionEditor)
            ->get(NoticeResource::getUrl('index'))
            ->assertSuccessful();

        $this->actingAs($conventionEditor)
            ->get(NoticeResource::getUrl('create'))
            ->assertSuccessful();

        $this->actingAs($conventionEditor)
            ->get(NoticeResource::getUrl('edit', ['record' => $notice]))
            ->assertSuccessful();
    }

    public function test_author_and_church_representative_cannot_access_notice_resource(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
        $notice = Notice::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($author)
            ->get(NoticeResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($author)
            ->get(NoticeResource::getUrl('create'))
            ->assertForbidden();

        $this->actingAs($author)
            ->get(NoticeResource::getUrl('edit', ['record' => $notice]))
            ->assertForbidden();

        $this->actingAs($churchRep)
            ->get(NoticeResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($churchRep)
            ->get(NoticeResource::getUrl('create'))
            ->assertForbidden();

        $this->actingAs($churchRep)
            ->get(NoticeResource::getUrl('edit', ['record' => $notice]))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(NoticeResource::getUrl('index'))
            ->assertRedirect('/admin/login');
    }

    public function test_can_render_notice_list_table_with_records_and_columns(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $notice = Notice::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Comunicado de Assembleia Geral',
            'priority' => NoticePriority::URGENT,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListNotices::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$notice])
            ->assertTableColumnExists('title')
            ->assertTableColumnExists('priority')
            ->assertTableColumnExists('validity_status')
            ->assertTableColumnExists('starts_at')
            ->assertTableColumnExists('expires_at')
            ->assertTableColumnExists('is_active');
    }

    public function test_can_create_notice_with_all_fields_and_auto_author(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateNotice::class)
            ->assertSuccessful()
            ->fillForm([
                'title' => 'Convocação Pastoral 2026',
                'slug' => 'convocacao-pastoral-2026',
                'content' => '<p>Conteúdo detalhado do comunicado oficial.</p>',
                'action_url' => 'https://cbngo.com.br/edital',
                'action_label' => 'Acessar Edital',
                'priority' => NoticePriority::HIGH,
                'starts_at' => Carbon::parse('2026-10-10 09:00:00'),
                'expires_at' => Carbon::parse('2026-10-20 18:00:00'),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('notices', [
            'title' => 'Convocação Pastoral 2026',
            'slug' => 'convocacao-pastoral-2026',
            'user_id' => $admin->id,
            'action_url' => 'https://cbngo.com.br/edital',
            'action_label' => 'Acessar Edital',
            'priority' => 'high',
            'is_active' => 1,
        ]);
    }

    public function test_cannot_create_notice_with_missing_required_fields(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateNotice::class)
            ->fillForm([
                'title' => null,
                'slug' => null,
                'content' => null,
                'priority' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['title', 'slug', 'content', 'priority']);
    }

    public function test_cannot_create_notice_with_expires_at_before_starts_at(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateNotice::class)
            ->fillForm([
                'title' => 'Aviso com datas inconsistentes',
                'slug' => 'aviso-datas-inconsistentes',
                'content' => '<p>Texto</p>',
                'priority' => NoticePriority::NORMAL,
                'starts_at' => Carbon::parse('2026-10-20 10:00:00'),
                'expires_at' => Carbon::parse('2026-10-10 10:00:00'),
            ])
            ->call('create')
            ->assertHasFormErrors(['expires_at']);
    }

    public function test_cannot_create_notice_with_duplicate_slug(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        Notice::factory()->create([
            'user_id' => $admin->id,
            'slug' => 'aviso-existente',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateNotice::class)
            ->fillForm([
                'title' => 'Outro Aviso',
                'slug' => 'aviso-existente',
                'content' => '<p>Texto</p>',
                'priority' => NoticePriority::NORMAL,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_can_update_notice(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $notice = Notice::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Título Antigo',
            'slug' => 'titulo-antigo',
            'content' => 'Conteúdo antigo',
            'priority' => NoticePriority::NORMAL,
        ]);

        Livewire::actingAs($admin)
            ->test(EditNotice::class, ['record' => $notice->getRouteKey()])
            ->assertSuccessful()
            ->assertFormSet([
                'title' => 'Título Antigo',
                'slug' => 'titulo-antigo',
            ])
            ->fillForm([
                'title' => 'Título Atualizado',
                'content' => '<p>Conteúdo atualizado com sucesso.</p>',
                'priority' => NoticePriority::URGENT,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'title' => 'Título Atualizado',
            'priority' => 'urgent',
        ]);
    }

    public function test_can_delete_notice(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $notice = Notice::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Aviso a ser removido',
        ]);

        Livewire::actingAs($admin)
            ->test(EditNotice::class, ['record' => $notice->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($notice);
    }
}
