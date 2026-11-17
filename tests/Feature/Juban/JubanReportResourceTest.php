<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanReportResource;
use App\Models\JubanReport;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class JubanReportResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('juban'));
    }

    public function test_authorized_personas_can_view_reports_list(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $secretary = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);

        $report = JubanReport::factory()->create([
            'year' => 2024,
            'quarter' => 1,
        ]);

        $this->actingAs($treasurer)
            ->get(JubanReportResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('2024 - Q1');

        $this->actingAs($president)
            ->get(JubanReportResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('2024 - Q1');

        $this->actingAs($superAdmin)
            ->get(JubanReportResource::getUrl('index'))
            ->assertSuccessful();

        $this->actingAs($secretary)
            ->get(JubanReportResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_unauthorized_roles_cannot_access_reports(): void
    {
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $minister = User::factory()->create(['role' => UserRole::MINISTER, 'is_active' => true]);

        $this->actingAs($author)->get(JubanReportResource::getUrl('index'))->assertForbidden();
        $this->actingAs($minister)->get(JubanReportResource::getUrl('index'))->assertForbidden();
    }

    public function test_treasurer_can_close_quarter_via_action(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        Carbon::setTestNow('2024-04-10 10:00:00');

        $this->actingAs($treasurer);

        Livewire::test(JubanReportResource\Pages\ListJubanReports::class)
            ->callAction('closeQuarter', [
                'year' => 2024,
                'quarter' => 1,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('juban_reports', [
            'year' => 2024,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA->value,
            'closed_by' => $treasurer->id,
        ]);

        Storage::disk('local')->assertExists('juban-reports/2024-q1-relatorio.pdf');
    }

    public function test_super_admin_can_reopen_quarter_via_action(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);

        $pdfPath = 'juban-reports/2024-q1-relatorio.pdf';
        Storage::disk('local')->put($pdfPath, 'dummy-pdf-content');

        $report = JubanReport::factory()->create([
            'year' => 2024,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
            'pdf_path' => $pdfPath,
            'pdf_sha256' => hash('sha256', 'dummy-pdf-content'),
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(JubanReportResource\Pages\ListJubanReports::class)
            ->callTableAction('reopen', $report, [
                'reason' => 'Erro cadastral corrigido',
            ])
            ->assertHasNoTableActionErrors();

        $report->refresh();

        $this->assertSame(JubanReportStatus::REABERTO, $report->status);
        $this->assertSame('Erro cadastral corrigido', $report->reopen_reason);
        $this->assertSame($superAdmin->id, $report->reopened_by);
        $this->assertNull($report->pdf_path);

        Storage::disk('local')->assertMissing($pdfPath);
    }

    public function test_authorized_user_can_download_generated_pdf(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $pdfPath = 'juban-reports/2024-q1-relatorio.pdf';
        Storage::disk('local')->put($pdfPath, 'conteudo-do-pdf-binario');

        $report = JubanReport::factory()->create([
            'year' => 2024,
            'quarter' => 1,
            'pdf_path' => $pdfPath,
            'pdf_sha256' => hash('sha256', 'conteudo-do-pdf-binario'),
        ]);

        $this->actingAs($treasurer);

        Livewire::test(JubanReportResource\Pages\ListJubanReports::class)
            ->callTableAction('downloadPdf', $report)
            ->assertFileDownloaded('relatorio-juban-2024-q1.pdf');
    }
}
