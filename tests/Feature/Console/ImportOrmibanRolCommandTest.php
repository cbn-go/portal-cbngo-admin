<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Minister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOrmibanRolCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = base_path('tests/Fixtures/ormiban/fichas_amostra.json');
    }

    public function test_imports_ministers_from_json_fixture_with_correct_data(): void
    {
        // Cria a igreja que coincide com o registro 1 da fixture
        $churchGyn = Church::factory()->create([
            'name' => 'Igreja Batista Nacional de Goiânia',
        ]);

        $this->artisan('ormiban:import-rol', ['path' => $this->fixturePath])
            ->expectsOutputToContain('Processando 3 fichas do rol Ormiban GO')
            ->expectsOutputToContain('Criados: 3')
            ->expectsOutputToContain('Atualizados: 0')
            ->assertSuccessful();

        $this->assertSame(3, Minister::count());

        // Verifica o primeiro ministro (Pastor Alfa)
        $alfa = Minister::where('registration_number', '9001')->first();
        $this->assertNotNull($alfa);
        $this->assertSame('11144477735', $alfa->cpf); // CPF normalizado apenas dígitos
        $this->assertSame('Pastor', $alfa->function);
        $this->assertSame('PASTOR TESTE ALFA', $alfa->name);
        $this->assertSame('1975-05-15', $alfa->birth_date?->format('Y-m-d'));
        $this->assertSame($churchGyn->id, $alfa->church_id); // Vinculado à igreja
        $this->assertNotNull($alfa->user_id);
        $this->assertNotNull($alfa->user);
        $this->assertSame(UserRole::MINISTER, $alfa->user->role);
        $this->assertSame('11144477735', $alfa->user->cpf);
        $this->assertTrue($alfa->user->must_set_password);
        $this->assertTrue($alfa->user->is_active);
        $this->assertSame('pastor.alfa@exemplo.com', $alfa->user->email);
        $this->assertCount(3, $alfa->familyMembers);
        $this->assertCount(2, $alfa->historyEvents);
        $this->assertNotNull($alfa->diplomaEvent());
        $this->assertNotNull($alfa->ordinationEvent());

        // Verifica o segundo ministro (Missionário Beta - sem correspondência de igreja)
        $beta = Minister::where('registration_number', '9002')->first();
        $this->assertNotNull($beta);
        $this->assertSame('22255588846', $beta->cpf);
        $this->assertSame('Missionário', $beta->function);
        $this->assertNull($beta->church_id); // Igreja inexistente
        $this->assertSame('Igreja Inexistente no Banco - CBN-GO', $beta->church_name);
        $this->assertCount(1, $beta->familyMembers);
        $this->assertCount(1, $beta->historyEvents);

        // Verifica o terceiro ministro (Pastor Gama - sem e-mail)
        $gama = Minister::where('registration_number', '9003')->first();
        $this->assertNotNull($gama);
        $this->assertSame('33366699957', $gama->cpf);
        $this->assertNull($gama->email);
        $this->assertNotNull($gama->user);
        $this->assertNull($gama->user->email);
        $this->assertSame(UserRole::MINISTER, $gama->user->role);
        $this->assertCount(0, $gama->familyMembers);
        $this->assertCount(2, $gama->historyEvents);
    }

    public function test_import_command_is_idempotent(): void
    {
        Church::factory()->create([
            'name' => 'Igreja Batista Nacional de Goiânia',
        ]);

        // Primeira execução: 3 criados
        $this->artisan('ormiban:import-rol', ['path' => $this->fixturePath])
            ->expectsOutputToContain('Criados: 3')
            ->assertSuccessful();

        $this->assertSame(3, Minister::count());

        // Segunda execução: 0 criados, 3 atualizados, nenhum duplicado
        $this->artisan('ormiban:import-rol', ['path' => $this->fixturePath])
            ->expectsOutputToContain('Criados: 0')
            ->expectsOutputToContain('Atualizados: 3')
            ->assertSuccessful();

        $this->assertSame(3, Minister::count());
    }

    public function test_fails_when_file_not_found(): void
    {
        $this->artisan('ormiban:import-rol', ['path' => 'caminho/inexistente.json'])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertFailed();
    }

    public function test_fails_when_file_contains_duplicate_cpfs(): void
    {
        $invalidFixture = [
            'total' => 2,
            'fichas' => [
                [
                    'matricula' => '1',
                    'funcao' => 'Pastor',
                    'nome' => 'Pastor Um',
                    'status' => 'ATIVO(A)',
                    'seccional' => 'ORMIBAN GO',
                    'igreja' => 'Igreja 1',
                    'dados_pessoais' => ['cpf' => '111.111.111-11'],
                ],
                [
                    'matricula' => '2',
                    'funcao' => 'Pastor',
                    'nome' => 'Pastor Dois',
                    'status' => 'ATIVO(A)',
                    'seccional' => 'ORMIBAN GO',
                    'igreja' => 'Igreja 2',
                    'dados_pessoais' => ['cpf' => '111.111.111-11'], // CPF duplicado
                ],
            ],
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'test_dup_').'.json';
        file_put_contents($tempPath, json_encode($invalidFixture));

        try {
            $this->artisan('ormiban:import-rol', ['path' => $tempPath])
                ->expectsOutputToContain('CPF duplicado encontrado no arquivo')
                ->assertFailed();
        } finally {
            @unlink($tempPath);
        }
    }
}
