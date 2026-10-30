<?php

namespace Database\Seeders;

use App\Enums\AmigoRegion;
use App\Enums\BoardGroup;
use App\Enums\ClergyTitle;
use App\Models\BoardMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Composição eleita e homologada na XXVI Assembleia Geral Ordinária
 * (Rio Verde, 12/09/2026) para o triênio 2026–2029.
 *
 * Idempotente: atualiza os membros do triênio pelo par nome + cargo + órgão e
 * desativa quem não pertence a ele. Rode em produção com backup prévio:
 * `php artisan db:seed --class=BoardTerm20262029Seeder --force`.
 */
class BoardTerm20262029Seeder extends Seeder
{
    public const TERM_STARTS_ON = '2026-10-01';

    public const TERM_ENDS_ON = '2029-09-30';

    /**
     * Ordem de exibição: órgãos na ordem de `BoardGroup` e, nas diretorias
     * regionais, Leste, Oeste, Norte e Sudoeste.
     *
     * @var list<array{0: ClergyTitle|null, 1: string, 2: string, 3: BoardGroup, 4?: AmigoRegion}>
     */
    private const MEMBERS = [
        [ClergyTitle::PASTOR, 'André Luiz Rodrigues da Silva', 'Presidente', BoardGroup::PRESIDENCIA],
        [ClergyTitle::PASTOR, 'Deusimar Pereira do Amaral', 'Vice-Presidente', BoardGroup::VICE_PRESIDENCIA],
        [ClergyTitle::PASTOR, 'Antônio Rogério Oliveira de Freitas', '1º Secretário de Atas', BoardGroup::SECRETARIA],
        [ClergyTitle::PASTOR, 'Welismar Alves Moreira', '2º Secretário de Atas', BoardGroup::SECRETARIA],

        [ClergyTitle::PASTOR, 'Wellington Coelho de Sousa', 'Vogal', BoardGroup::CONPLEX],
        [ClergyTitle::PASTOR, 'Waldir Sebastião Assis', 'Vogal', BoardGroup::CONPLEX],
        [null, 'Allan Kardec Pereira de Assis', 'Vogal', BoardGroup::CONPLEX],
        [null, 'Irenice Ramos', 'Vogal', BoardGroup::CONPLEX],

        [null, 'Abílio Rocha Neto', 'Relator', BoardGroup::CONSELHO_FISCAL],
        [null, 'Juarez Raimundo de Araújo', '1º Vogal', BoardGroup::CONSELHO_FISCAL],
        [null, 'Marilane Cristina Jacintho e Braga', '2ª Vogal', BoardGroup::CONSELHO_FISCAL],
        [null, 'Cristiano Sales de Carvalho', '1º Suplente', BoardGroup::CONSELHO_FISCAL],
        [null, 'Marcos Fialho Silva', '2º Suplente', BoardGroup::CONSELHO_FISCAL],
        [null, 'Sueli Burin Redivo', '3ª Suplente', BoardGroup::CONSELHO_FISCAL],

        [ClergyTitle::PASTOR, 'Reidner Pereira de Almeida', 'Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::LESTE],
        [ClergyTitle::PASTOR, 'Jamil Aparecido Vieira da Silva', 'Vice-Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::LESTE],
        [ClergyTitle::PASTOR, 'Rone Rogers Garcês Dias da Silva', '1º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::LESTE],
        [ClergyTitle::EVANGELISTA, 'Fábio Oliveira Nascimento', '2º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::LESTE],

        [ClergyTitle::PASTOR, 'Maxsuel Ferreira Ramos', 'Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::OESTE],
        [ClergyTitle::PASTOR, 'Fabiano Antônio Pinto', 'Vice-Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::OESTE],
        [ClergyTitle::PASTOR, 'Paulo Cézar de Oliveira', '1º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::OESTE],
        [ClergyTitle::PASTOR, 'Marcos Fialho Silva', '2º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::OESTE],

        [ClergyTitle::PASTOR, 'Adonias Ferreira Leite', 'Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::NORTE],
        [ClergyTitle::PASTOR, 'Manoel de Souza Dourado Neto', 'Vice-Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::NORTE],
        [ClergyTitle::PASTOR, 'Alessandro Gonzaga Silva', '1º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::NORTE],
        [ClergyTitle::PASTOR, 'Fábio Teixeira dos Santos', '2º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::NORTE],

        [ClergyTitle::PASTOR, 'Marcondes Nunes Pereira', 'Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::SUDOESTE],
        [ClergyTitle::PASTOR, 'José Wilson de Oliveira', 'Vice-Coordenador', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::SUDOESTE],
        [ClergyTitle::PASTOR, 'Francisco Marques da Silva Neto', '1º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::SUDOESTE],
        [ClergyTitle::EVANGELISTA, 'Elcio Luiz Miranda Sobrinho', '2º Secretário', BoardGroup::COORDENACAO_REGIONAL, AmigoRegion::SUDOESTE],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $ids = [];

            foreach (self::MEMBERS as $index => $member) {
                [$title, $name, $role, $group] = $member;

                // Reaproveita o registro do triênio ou um cadastrado à mão sem
                // datas. whereDate: o SQLite guarda a data com horário, o MySQL não.
                $record = BoardMember::query()
                    ->where(['name' => $name, 'role' => $role, 'group' => $group->value])
                    ->where(fn ($query) => $query->whereDate('term_starts_on', self::TERM_STARTS_ON)->orWhereNull('term_starts_on'))
                    ->orderByDesc('term_starts_on')
                    ->first() ?? new BoardMember(['name' => $name, 'role' => $role, 'group' => $group]);

                $record->fill([
                    'title' => $title,
                    'region' => $member[4] ?? null,
                    'term_starts_on' => self::TERM_STARTS_ON,
                    'term_ends_on' => self::TERM_ENDS_ON,
                    'position' => $index + 1,
                    'is_active' => true,
                ])->save();

                $ids[] = $record->id;
            }

            // Gestões anteriores (ou dados de exemplo) saem do portal, mas ficam
            // no painel como histórico. As posições vão para depois do triênio.
            $offset = count(self::MEMBERS);

            BoardMember::query()->whereNotIn('id', $ids)->orderBy('position')->get()
                ->each(function (BoardMember $member, int $index) use ($offset): void {
                    $member->update(['is_active' => false, 'position' => $offset + $index + 1]);
                });
        });
    }

    public static function memberCount(): int
    {
        return count(self::MEMBERS);
    }
}
