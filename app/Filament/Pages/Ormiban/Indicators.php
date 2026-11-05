<?php

namespace App\Filament\Pages\Ormiban;

use App\Filament\Ormiban\Widgets\AgeChartWidget;
use App\Filament\Ormiban\Widgets\MinisterSummaryWidget;
use App\Filament\Ormiban\Widgets\MinistryAgeChartWidget;
use App\Models\Minister;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Widgets\Widget;

class Indicators extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Indicadores';

    protected static ?string $title = 'Ormiban / Indicadores';

    protected static ?string $slug = 'ormiban/indicadores';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.ormiban.indicators';

    public function getSubheading(): ?string
    {
        $timezone = (string) config('app.timezone');

        return "Anos completos até hoje, no fuso {$timezone}.";
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && $user->role->hasAdminPanelAccess();
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('function')
                    ->label('Função')
                    ->options(fn (): array => Minister::query()
                        ->whereNotNull('function')
                        ->distinct()
                        ->orderBy('function')
                        ->pluck('function', 'function')
                        ->all())
                    ->placeholder('Todas')
                    ->native(false),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        Minister::STATUS_ACTIVE => 'Ativo(a)',
                        Minister::STATUS_INACTIVE => 'Inativo(a)',
                    ])
                    ->placeholder('Todos')
                    ->native(false),
            ]);
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function getIndicatorWidgets(): array
    {
        return [
            MinisterSummaryWidget::class,
            AgeChartWidget::class,
            MinistryAgeChartWidget::class,
        ];
    }
}
