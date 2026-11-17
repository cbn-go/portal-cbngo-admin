<?php

namespace Tests\Feature\Juban;

use App\Enums\UserRole;
use App\Filament\Juban\Pages\ManagePasskeys;
use App\Models\Passkey;
use App\Models\User;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class ManagePasskeysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cbngo.org.br',
            'app.name' => 'CBN-GO',
        ]);
        Filament::setCurrentPanel(Filament::getPanel('juban'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/juban/passkeys')->assertRedirect('/juban/login');
    }

    public function test_unauthorized_roles_are_forbidden(): void
    {
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $this->actingAs($author)->get('/juban/passkeys')->assertForbidden();

        $minister = User::factory()->create(['role' => UserRole::MINISTER, 'is_active' => true]);
        $this->actingAs($minister)->get('/juban/passkeys')->assertForbidden();
    }

    public function test_inactive_authorized_persona_is_forbidden(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => false]);
        $this->actingAs($treasurer)->get('/juban/passkeys')->assertForbidden();
    }

    public function test_authorized_personas_can_view_passkeys_page(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        $this->actingAs($treasurer)->get('/juban/passkeys')->assertSuccessful()->assertSee('Minhas passkeys');
        $this->actingAs($president)->get('/juban/passkeys')->assertSuccessful()->assertSee('Minhas passkeys');

        $this->actingAs($treasurer);
        Livewire::test(ManagePasskeys::class)->assertSuccessful();

        $this->actingAs($president);
        Livewire::test(ManagePasskeys::class)->assertSuccessful();
    }

    public function test_page_lists_only_own_passkeys(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        Passkey::factory()->create([
            'user_id' => $user->id,
            'name' => 'Meu Windows Hello',
        ]);
        Passkey::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Chave Secreta Invasor',
        ]);

        $this->actingAs($user);

        Livewire::test(ManagePasskeys::class)
            ->assertSee('Meu Windows Hello')
            ->assertDontSee('Chave Secreta Invasor');
    }

    public function test_start_registration_requires_valid_password_confirmation(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::JUBAN_TREASURER,
            'is_active' => true,
            'password' => Hash::make('senha-secreta-123'),
        ]);

        $this->actingAs($user);

        // Tentativa com senha errada
        Livewire::test(ManagePasskeys::class)
            ->call('startRegistration', 'Meu Notebook', 'senha-incorreta')
            ->assertHasErrors(['password']);

        // Tentativa com senha correta
        Livewire::test(ManagePasskeys::class)
            ->call('startRegistration', 'Meu Notebook', 'senha-secreta-123')
            ->assertHasNoErrors()
            ->assertDispatched('init-webauthn-registration');

        $this->assertNotNull(Cache::get("passkey_challenge_{$user->id}"));
    }

    public function test_finish_registration_creates_passkey_via_livewire(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::JUBAN_TREASURER,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $rawChallenge = random_bytes(32);
        $challenge = Base64UrlSafe::encodeUnpadded($rawChallenge);
        Cache::put("passkey_challenge_{$user->id}", $challenge, 300);

        $credentialId = random_bytes(32);
        $encodedCredId = Base64UrlSafe::encodeUnpadded($credentialId);

        // Constrói payload sintético válido
        $rpIdHash = hash('sha256', 'cbngo.org.br', true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV | AuthenticatorData::FLAG_AT | AuthenticatorData::FLAG_BE);
        $signCount = pack('N', 0);
        $aaguid = hex2bin('0102030405060708090a0b0c0d0e0f10');
        $credIdLength = pack('n', strlen($credentialId));

        $coseKeyMap = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(random_bytes(32)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(random_bytes(32)));

        $attestedCredentialData = $aaguid.$credIdLength.$credentialId.(string) $coseKeyMap;
        $authData = $rpIdHash.$flagsByte.$signCount.$attestedCredentialData;

        $attestationMap = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        $payload = [
            'id' => $encodedCredId,
            'rawId' => $encodedCredId,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => $challenge,
                    'origin' => 'https://cbngo.org.br',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestationMap),
                'transports' => ['internal'],
            ],
        ];

        Livewire::test(ManagePasskeys::class)
            ->call('finishRegistration', 'Touch ID MacBook', $payload)
            ->assertHasNoErrors()
            ->assertNotified('Passkey cadastrada com sucesso!');

        $this->assertDatabaseHas('passkeys', [
            'user_id' => $user->id,
            'name' => 'Touch ID MacBook',
            'credential_id' => $encodedCredId,
        ]);
    }

    public function test_user_can_rename_own_passkey(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $passkey = Passkey::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nome Antigo',
        ]);

        $this->actingAs($user);

        Livewire::test(ManagePasskeys::class)
            ->call('renamePasskey', $passkey->id, 'Nome Atualizado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('passkeys', [
            'id' => $passkey->id,
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_user_cannot_rename_or_delete_other_users_passkey(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);
        $otherPasskey = Passkey::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Passkey do Outro',
        ]);

        $this->actingAs($user);

        Livewire::test(ManagePasskeys::class)
            ->call('renamePasskey', $otherPasskey->id, 'Tentativa de Hack')
            ->assertForbidden();

        $this->assertDatabaseHas('passkeys', [
            'id' => $otherPasskey->id,
            'name' => 'Passkey do Outro',
        ]);

        Livewire::test(ManagePasskeys::class)
            ->call('deletePasskey', $otherPasskey->id)
            ->assertForbidden();

        $this->assertDatabaseHas('passkeys', [
            'id' => $otherPasskey->id,
        ]);
    }

    public function test_user_can_delete_own_passkey(): void
    {
        $user = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $passkey = Passkey::factory()->create([
            'user_id' => $user->id,
            'name' => 'Passkey Para Deletar',
        ]);

        $this->actingAs($user);

        Livewire::test(ManagePasskeys::class)
            ->call('deletePasskey', $passkey->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('passkeys', [
            'id' => $passkey->id,
        ]);
    }
}
