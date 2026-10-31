<?php

namespace App\Filament\Resources\MunicipalityResource\Pages;

use App\Enums\PriorityBand;
use App\Enums\StrategyIndicator;
use App\Filament\Concerns\HasStrategySourceFooter;
use App\Filament\Resources\MunicipalityResource;
use App\Models\Municipality;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListMunicipalities extends ListRecords
{
    use HasStrategySourceFooter;

    protected static string $resource = MunicipalityResource::class;

    protected ?string $subheading = 'Indicadores do IBGE e cobertura da CBN-GO nos 246 municípios de Goiás.';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /**
     * Exporta a listagem com os filtros, a busca e a ordenação atuais.
     * Separador ";" e BOM UTF-8 para abrir direto no Excel em português.
     */
    public function exportCsv(): StreamedResponse
    {
        $records = $this->getFilteredSortedTableQuery()->get();
        $coverage = MunicipalityResource::coverage();

        $decimal = fn (int|float|string|null $value, int $decimals = 2): string => $value === null || $value === ''
            ? ''
            : number_format((float) $value, $decimals, ',', '');

        return response()->streamDownload(function () use ($records, $coverage, $decimal): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Município',
                'Código IBGE',
                'Região imediata',
                'Região intermediária',
                'População',
                'Igrejas ativas',
                'Habitantes por igreja',
                'Não evangélicos',
                'Não evangélicos por igreja',
                StrategyIndicator::POPULATION_GROWTH->label().' (%)',
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022->label(),
                StrategyIndicator::RELIGION_BAPTIST_PCT_2010->label(),
                StrategyIndicator::PIB_PER_CAPITA->label().' (R$)',
                'Projetos sociais publicados',
                'Índice de prioridade',
                'Faixa de prioridade',
            ], ';');

            /** @var Municipality $municipality */
            foreach ($records as $municipality) {
                $item = $coverage->get($municipality->id);

                fputcsv($out, [
                    $municipality->name,
                    $municipality->ibge_code,
                    $municipality->immediate_region_name,
                    $municipality->intermediate_region_name,
                    $item?->population,
                    $item->activeChurchesCount ?? 0,
                    $decimal($item?->inhabitantsPerChurch, 0),
                    $item?->nonEvangelicalPopulation,
                    $decimal($item?->nonEvangelicalsPerChurch, 0),
                    $decimal($municipality->getAttribute('growth')),
                    $decimal($municipality->getAttribute('evangelical_pct')),
                    $decimal($municipality->getAttribute('baptist_pct_2010')),
                    $decimal($municipality->getAttribute('pib_per_capita')),
                    $item->publishedSocialProjectsCount ?? 0,
                    $decimal($item?->priorityIndex),
                    PriorityBand::forScore($item?->priorityIndex)?->getLabel(),
                ], ';');
            }

            fclose($out);
        }, 'municipios-goias-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
