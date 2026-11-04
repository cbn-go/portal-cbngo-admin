<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Minister;
use App\Models\Municipality;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportOrmibanRol extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ormiban:import-rol
                            {path? : Caminho do arquivo JSON das fichas}
                            {--force : Forçar atualização de registros}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa o rol de ministros ativos da Ormiban GO a partir do JSON oficial';

    /**
     * Mapa de códigos de histórico para descrições canônicas corrigidas em UTF-8.
     *
     * @var array<array-key, string>
     */
    protected const HISTORY_CODE_MAP = [
        '0' => 'MEMBRESIA',
        '1' => 'DIPLOMADO',
        '2' => 'ORDENAÇÃO',
        '3' => 'REINTEGRAÇÃO',
        '7' => 'AUSÊNCIA NÃO JUSTIFICADA',
        '10' => 'RECEBIDO POR TRANSFERÊNCIA',
        '11' => 'ISENTO ESPECIAL POR IDADE',
        '12' => 'ISENTO ESPECIAL POR RENDA',
        '13' => 'FILIAÇÃO',
        '14' => 'DESLIGADO',
        '15' => 'CONSAGRAÇÃO',
        '17' => 'PEDIDO DE LICENÇA',
        '18' => 'DESCONTO OU ANISTIA DE DÉBITOS',
        '20' => 'TRANSFERÊNCIA (EM TRÂNSITO)',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = (string) ($this->argument('path') ?: database_path('data/FICHAS CADASTROS ATIVOS GO.json'));

        if (! file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");

            return self::FAILURE;
        }

        $jsonContent = (string) file_get_contents($filePath);
        $data = json_decode($jsonContent, true);

        if (! is_array($data) || ! isset($data['fichas']) || ! is_array($data['fichas'])) {
            $this->error("Formato inválido do arquivo JSON: chave 'fichas' não encontrada.");

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $fichas */
        $fichas = $data['fichas'];
        $totalFichas = count($fichas);
        $this->info("Processando {$totalFichas} fichas do rol Ormiban GO...");

        // 1. Validação de Unicidade de CPFs
        $cpfMap = [];
        foreach ($fichas as $index => $ficha) {
            $rawCpf = $ficha['dados_pessoais']['cpf'] ?? null;
            $cleanCpf = $this->normalizeCpf($rawCpf);

            if ($cleanCpf === null) {
                $matricula = $ficha['matricula'] ?? "índice {$index}";
                $this->error("Ficha da matrícula {$matricula} não possui CPF válido.");

                return self::FAILURE;
            }

            if (isset($cpfMap[$cleanCpf])) {
                $dupMatricula = $ficha['matricula'] ?? 'desconhecida';
                $this->error("CPF duplicado encontrado no arquivo: {$cleanCpf} (matrícula {$dupMatricula} e {$cpfMap[$cleanCpf]}).");

                return self::FAILURE;
            }

            $cpfMap[$cleanCpf] = $ficha['matricula'] ?? (string) $index;
        }

        // 2. Carrega igrejas para busca best-effort
        /** @var Collection<int, Church> $churches */
        $churches = Church::all();

        $createdCount = 0;
        $updatedCount = 0;
        $unmatchedChurchCount = 0;
        /** @var array<string, int> $unmatchedChurches */
        $unmatchedChurches = [];

        foreach ($fichas as $ficha) {
            $cpf = (string) $this->normalizeCpf($ficha['dados_pessoais']['cpf'] ?? null);
            $matricula = (string) ($ficha['matricula'] ?? '');
            $rawChurchName = (string) ($ficha['igreja'] ?? '');

            $matchedChurch = $this->matchChurch($rawChurchName, $churches);
            $churchId = $matchedChurch?->id;

            if ($churchId === null && filled($rawChurchName)) {
                $unmatchedChurchCount++;
                $unmatchedChurches[$rawChurchName] = ($unmatchedChurches[$rawChurchName] ?? 0) + 1;
            }

            $dadosPessoais = $ficha['dados_pessoais'] ?? [];

            $attributes = [
                'registration_number' => $matricula,
                'function' => $this->normalizeFunction((string) ($ficha['funcao'] ?? 'Pastor')),
                'name' => trim((string) ($ficha['nome'] ?? '')),
                'status' => trim((string) ($ficha['status'] ?? 'ATIVO(A)')),
                'sectional' => trim((string) ($ficha['seccional'] ?? 'ORMIBAN GO')),
                'church_name' => $rawChurchName,
                'address' => $this->nullIfBlank($ficha['endereco'] ?? null),
                'zip_code' => $this->nullIfBlank($ficha['cep'] ?? null),
                'phone' => $this->nullIfBlank($ficha['telefone'] ?? null),
                'email' => $this->nullIfBlank($ficha['email'] ?? null),
                'cpf' => $cpf,
                'birth_date' => $this->parseDate($dadosPessoais['data_nascimento'] ?? null),
                'nationality' => $this->nullIfBlank($dadosPessoais['nacionalidade'] ?? null),
                'place_of_birth' => $this->nullIfBlank($dadosPessoais['naturalidade'] ?? null),
                'education_level' => $this->nullIfBlank($dadosPessoais['grau_instrucao'] ?? null),
                'marital_status' => $this->nullIfBlank($dadosPessoais['estado_civil'] ?? null),
                'gender' => $this->nullIfBlank($dadosPessoais['sexo'] ?? null),
                'profession' => $this->nullIfBlank($dadosPessoais['profissao'] ?? null),
                'identity_document' => $this->nullIfBlank($dadosPessoais['identidade'] ?? null),
                'identity_issuer' => $this->nullIfBlank($dadosPessoais['orgao_emissor'] ?? null),
                'passport' => $this->nullIfBlank($dadosPessoais['passaporte'] ?? null),
                'identity_issued_at' => $this->parseDate($dadosPessoais['data_emissao_ci'] ?? null),
                'other_phones' => ! empty($ficha['outros_telefones']) ? $ficha['outros_telefones'] : null,
                'occupational_roles' => ! empty($ficha['funcoes_ocupacionais']) ? $ficha['funcoes_ocupacionais'] : null,
                'registered_at' => $this->parseDateTime($dadosPessoais['data_cadastro'] ?? null),
                'source_updated_at' => $this->parseDateTime($dadosPessoais['ultima_alteracao'] ?? null),
                'church_id' => $churchId,
            ];

            /** @var Minister|null $minister */
            $minister = Minister::query()
                ->where('cpf', $cpf)
                ->orWhere('registration_number', $matricula)
                ->first();

            if ($minister) {
                $minister->update($attributes);
                $updatedCount++;
            } else {
                $minister = Minister::create($attributes);
                $createdCount++;
            }

            // Sincroniza Familiares
            $minister->familyMembers()->delete();
            if (! empty($ficha['familiares']) && is_array($ficha['familiares'])) {
                foreach ($ficha['familiares'] as $fam) {
                    if (blank($fam['nome'] ?? null)) {
                        continue;
                    }

                    $minister->familyMembers()->create([
                        'relationship' => $this->normalizeRelationship((string) ($fam['parentesco'] ?? '')),
                        'name' => trim((string) $fam['nome']),
                        'birth_date' => $this->parseDate($fam['data_nascimento'] ?? null),
                    ]);
                }
            }

            // Sincroniza Histórico de Eventos
            $minister->historyEvents()->delete();
            if (! empty($ficha['historico']) && is_array($ficha['historico'])) {
                foreach ($ficha['historico'] as $event) {
                    $occurredOn = $this->parseDate($event['data'] ?? null);
                    if ($occurredOn === null) {
                        continue;
                    }

                    $code = trim((string) ($event['codigo'] ?? ''));
                    $desc = self::HISTORY_CODE_MAP[$code] ?? trim((string) ($event['descricao'] ?? ''));

                    $minister->historyEvents()->create([
                        'occurred_on' => $occurredOn,
                        'code' => $code,
                        'description' => $desc,
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info('--- Resumo da Importação Ormiban GO ---');
        $this->line("Total de fichas: {$totalFichas}");
        $this->line("Criados: {$createdCount}");
        $this->line("Atualizados: {$updatedCount}");
        $this->line("Sem casamento de igreja: {$unmatchedChurchCount}");
        $this->line('CPFs duplicados no arquivo: 0');

        if (! empty($unmatchedChurches)) {
            $this->newLine();
            $this->warn('Igrejas não vinculadas automaticamente no cadastro:');
            foreach ($unmatchedChurches as $name => $count) {
                $this->line(" - {$name} ({$count} ministros)");
            }
        }

        $this->newLine();
        $this->info('Importação concluída com sucesso!');

        return self::SUCCESS;
    }

    /**
     * Normaliza CPF deixando apenas 11 dígitos numéricos.
     */
    protected function normalizeCpf(?string $cpf): ?string
    {
        if (blank($cpf)) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $cpf);

        if ($digits === '') {
            return null;
        }

        return str_pad($digits, 11, '0', STR_PAD_LEFT);
    }

    /**
     * Normaliza a função pastoral/ministerial.
     */
    protected function normalizeFunction(string $function): string
    {
        $func = trim($function);
        if (Str::contains($func, 'Mission', true)) {
            return 'Missionário';
        }

        return 'Pastor';
    }

    /**
     * Normaliza o parentesco de familiares.
     */
    protected function normalizeRelationship(string $relationship): string
    {
        $rel = trim($relationship);

        if (Str::contains($rel, ['Cnjuge', 'Cnjuge', 'Conjuge', 'Espos'], true)) {
            return 'Cônjuge';
        }
        if (Str::contains($rel, ['Me', 'Mae'], true)) {
            return 'Mãe';
        }
        if (Str::contains($rel, 'Pai', true)) {
            return 'Pai';
        }
        if (Str::contains($rel, 'Filha', true)) {
            return 'Filha';
        }
        if (Str::contains($rel, 'Filho', true)) {
            return 'Filho';
        }
        if (Str::contains($rel, ['Irm', 'Irma'], true)) {
            return 'Irmã';
        }
        if (Str::contains($rel, ['Irmo', 'Irmao'], true)) {
            return 'Irmão';
        }

        return $rel;
    }

    /**
     * Tenta vincular o nome de igreja do rol com uma igreja da tabela churches.
     *
     * @param  Collection<int, Church>  $churches
     */
    protected function matchChurch(string $churchName, Collection $churches): ?Church
    {
        if (blank($churchName)) {
            return null;
        }

        // 1. Limpa sufixos conhecidos (ex: " - CBN-GO")
        $clean = (string) preg_replace('/\s*-\s*CBN-GO\s*$/i', '', $churchName);
        $normalizedSearch = Municipality::normalizeName($clean);

        // 2. Busca exata por nome normalizado
        foreach ($churches as $church) {
            $churchNorm = Municipality::normalizeName($church->name);
            if ($churchNorm === $normalizedSearch) {
                return $church;
            }
        }

        // 3. Tenta remover "Congregação Batista" ou "Igreja Batista"
        $strippedSearch = (string) preg_replace('/^(congregao|igreja|congregao batista|igreja batista)\s+/i', '', $clean);
        $strippedNorm = Municipality::normalizeName($strippedSearch);

        if (filled($strippedNorm)) {
            foreach ($churches as $church) {
                $churchNorm = Municipality::normalizeName($church->name);
                if (Str::contains($churchNorm, $strippedNorm)) {
                    return $church;
                }
            }
        }

        return null;
    }

    /**
     * Converte datas d/m/Y para Y-m-d.
     */
    protected function parseDate(?string $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        $trimmed = trim($date);

        // Se contiver horário, pega apenas a primeira parte
        if (str_contains($trimmed, ' ')) {
            $parts = explode(' ', $trimmed);
            $trimmed = $parts[0];
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Converte datas d/m/Y H:i:s ou d/m/Y para Y-m-d H:i:s.
     */
    protected function parseDateTime(?string $dateTime): ?string
    {
        if (blank($dateTime)) {
            return null;
        }

        $trimmed = trim($dateTime);

        try {
            if (str_contains($trimmed, ':')) {
                return Carbon::createFromFormat('d/m/Y H:i:s', $trimmed)->format('Y-m-d H:i:s');
            }

            return Carbon::createFromFormat('d/m/Y', $trimmed)->startOfDay()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nullIfBlank(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
