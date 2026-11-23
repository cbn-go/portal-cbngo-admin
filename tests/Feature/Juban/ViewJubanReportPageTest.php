<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanReportResource\Pages\ViewJubanReport;
use App\Models\JubanReport;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class ViewJubanReportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cbngo.org.br',
            'app.name' => 'CBN-GO',
        ]);

        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('juban'));
    }

    private function createReportPdf(JubanReport $report, string $content = 'Official Report PDF'): string
    {
        $path = "reports/juban/report-{$report->year}-q{$report->quarter}.pdf";
        Storage::disk('local')->put($path, $content);
        $hash = hash('sha256', $content);

        $report->update([
            'pdf_path' => $path,
            'pdf_sha256' => $hash,
        ]);

        return $hash;
    }

    public function test_authorized_personas_can_view_report_detail_page(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        $this->actingAs($treasurer)
            ->get("/juban/relatorios/{$report->id}")
            ->assertOk()
            ->assertSee("Q{$report->quarter}/{$report->year}")
            ->assertSee('Aguardando Tesoureiro');
    }

    public function test_unauthorized_roles_cannot_view_report_detail(): void
    {
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $report = JubanReport::factory()->create();

        $this->actingAs($author)
            ->get("/juban/relatorios/{$report->id}")
            ->assertForbidden();
    }

    public function test_timeline_reflects_report_status_and_signatures(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
            'closed_at' => now(),
            'closed_by' => $treasurer->id,
        ]);
        $this->createReportPdf($report);

        $this->actingAs($treasurer);

        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertSee('Fechamento Contábil')
            ->assertSee($treasurer->name)
            ->assertSee('Assinatura da Tesouraria')
            ->assertSee('Aguardando assinatura da tesouraria');
    }

    public function test_sign_button_is_visible_only_to_current_signers_turn(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        Passkey::factory()->create(['user_id' => $treasurer->id]);
        Passkey::factory()->create(['user_id' => $president->id]);

        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        // 1. Tesoureiro na vez da tesouraria: vê botão de assinar
        $this->actingAs($treasurer);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('sign');

        // 2. Presidente na vez da tesouraria: não vê botão de assinar
        $this->actingAs($president);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionHidden('sign');
    }

    public function test_shows_missing_passkey_warning_when_user_has_no_credentials(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        // Usuário não tem nenhuma passkey
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        $this->actingAs($treasurer);

        $test = Livewire::test(ViewJubanReport::class, ['record' => $report->id]);
        $this->assertTrue($test->instance()->canSignReport());
        $this->assertFalse($test->instance()->userHasPasskeys());

        $test->assertSee('Você precisa cadastrar uma passkey antes de assinar')
            ->assertSee('/juban/passkeys');
    }

    public function test_verify_pdf_action_validates_matching_hash_successfully(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create();
        $this->createReportPdf($report, 'Original Valid Content');

        $this->actingAs($treasurer);

        $uploadedFile = UploadedFile::fake()->createWithContent('document.pdf', 'Original Valid Content');

        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->callAction('verifyPdf', [
                'pdf_file' => $uploadedFile,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('PDF autêntico e íntegro!');
    }

    public function test_verify_pdf_action_warns_on_hash_mismatch(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create();
        $this->createReportPdf($report, 'Original Valid Content');

        $this->actingAs($treasurer);

        $tamperedFile = UploadedFile::fake()->createWithContent('tampered.pdf', 'Modified Tampered Content');

        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->callAction('verifyPdf', [
                'pdf_file' => $tamperedFile,
            ])
            ->assertNotified('Atenção: Hash divergente!');
    }

    public function test_download_receipt_pdf_action_is_available_when_signatures_exist(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_PRESIDENCIA]);
        $this->createReportPdf($report);

        JubanReportSignature::factory()->create([
            'report_id' => $report->id,
            'user_id' => $treasurer->id,
            'role' => JubanSigningRole::TESOURARIA,
        ]);

        $this->actingAs($treasurer);

        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('downloadReceiptPdf');
    }

    public function test_treasurer_can_trigger_webauthn_signing_and_complete_flow_via_livewire(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        $keyData = $this->createPasskeyForUser($treasurer);

        $this->actingAs($treasurer);

        $component = Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->callAction('sign')
            ->assertDispatched('init-webauthn-signing');

        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $this->assertNotNull($cached);

        $payload = $this->buildAssertionPayload($keyData, $cached['challenge_binary']);

        $component->call('finishSigning', $payload)
            ->assertNotified('Relatório assinado com sucesso!');

        $this->assertEquals(JubanReportStatus::AGUARDANDO_PRESIDENCIA, $report->fresh()->status);
        $this->assertDatabaseHas('juban_report_signatures', [
            'report_id' => $report->id,
            'user_id' => $treasurer->id,
            'role' => 'tesouraria',
        ]);
    }

    private function createPasskeyForUser(User $user): array
    {
        $conf = file_exists('C:/Program Files/Git/usr/ssl/openssl.cnf') ? 'C:/Program Files/Git/usr/ssl/openssl.cnf' : null;
        $configArgs = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if ($conf) {
            $configArgs['config'] = $conf;
        }

        $key = openssl_pkey_new($configArgs);
        $details = openssl_pkey_get_details($key);
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];

        $coseKeyMap = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));

        $passkey = Passkey::factory()->create([
            'user_id' => $user->id,
            'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
            'public_key' => Base64UrlSafe::encodeUnpadded((string) $coseKeyMap),
            'sign_count' => 0,
        ]);

        return ['passkey' => $passkey, 'key' => $key];
    }

    private function buildAssertionPayload(array $keyData, string $challengeBinary): array
    {
        $clientDataJSON = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challengeBinary),
            'origin' => 'https://cbngo.org.br',
        ], JSON_THROW_ON_ERROR);

        $rpIdHash = hash('sha256', 'cbngo.org.br', true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV);
        $signCountBytes = pack('N', 1);
        $authData = $rpIdHash.$flagsByte.$signCountBytes;

        $dataToSign = $authData.hash('sha256', $clientDataJSON, true);
        openssl_sign($dataToSign, $rawSignature, $keyData['key'], OPENSSL_ALGO_SHA256);

        return [
            'id' => $keyData['passkey']->credential_id,
            'rawId' => $keyData['passkey']->credential_id,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientDataJSON),
                'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
                'signature' => Base64UrlSafe::encodeUnpadded($rawSignature),
            ],
        ];
    }
}
