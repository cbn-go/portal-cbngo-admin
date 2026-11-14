<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class ProductionDataLeakTest extends TestCase
{
    public function test_no_production_json_files_are_tracked_in_git(): void
    {
        $output = [];
        $exitCode = 0;
        exec('git ls-files', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'git ls-files should exit successfully');

        $trackedFiles = array_filter($output);

        // Nenhuma ficha real de produção pode estar rastreada no Git
        foreach ($trackedFiles as $file) {
            $normalized = str_replace('\\', '/', $file);

            if (str_contains(strtolower($normalized), 'ficha')) {
                // A única exceção permitida é a fixture sintética anonimizada de teste
                $this->assertSame(
                    'tests/Fixtures/ormiban/fichas_amostra.json',
                    $normalized,
                    "Arquivo com potencial dado de produção rastreado indevidamente: {$file}"
                );
            }
        }
    }

    public function test_sample_fixture_contains_only_synthetic_anonymized_data(): void
    {
        $fixturePath = base_path('tests/Fixtures/ormiban/fichas_amostra.json');
        $this->assertFileExists($fixturePath);

        $content = (string) file_get_contents($fixturePath);
        /** @var array{fichas: array<int, array{dados_pessoais: array{cpf: string}, email: string|null, nome: string}>, total: int} $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        // Amostra sintética de 3 fichas
        $this->assertSame(3, $data['total']);
        $this->assertCount(3, $data['fichas']);

        foreach ($data['fichas'] as $ficha) {
            // Nomes devem ser expressamente sintéticos para testes
            $this->assertStringContainsString('TESTE', $ficha['nome']);

            // E-mails quando presentes devem ser de domínio de teste reservado
            if ($ficha['email'] !== null) {
                $this->assertStringEndsWith('@exemplo.com', $ficha['email']);
            }
        }
    }

    public function test_gitignore_protects_ormiban_production_data(): void
    {
        $gitignorePath = base_path('.gitignore');
        $this->assertFileExists($gitignorePath);

        $content = (string) file_get_contents($gitignorePath);

        $this->assertStringContainsString('*FICHAS*', $content);
        $this->assertStringContainsString('*fichas*', $content);
        $this->assertStringContainsString('/storage/app/ormiban/', $content);
    }
}
