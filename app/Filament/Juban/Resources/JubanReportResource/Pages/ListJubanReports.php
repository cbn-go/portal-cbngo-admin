<?php

namespace App\Filament\Juban\Resources\JubanReportResource\Pages;

use App\Filament\Juban\Resources\JubanReportResource;
use App\Models\JubanReport;
use App\Services\Juban\Exceptions\QuarterlyClosingException;
use App\Services\Juban\QuarterlyClosingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListJubanReports extends ListRecords
{
    protected static string $resource = JubanReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeQuarter')
                ->label('Fechar Trimestre')
                ->icon('heroicon-m-lock-closed')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create', JubanReport::class) ?? false)
                ->modalHeading('Fechar Trimestre Contábil')
                ->modalDescription('O fechamento irá congelar o livro-caixa, gerar o PDF oficial e travar o período contra alterações retroativas.')
                ->form([
                    Forms\Components\Select::make('year')
                        ->label('Ano')
                        ->options(function (): array {
                            $current = now()->year;

                            return [
                                $current => (string) $current,
                                $current - 1 => (string) ($current - 1),
                                $current - 2 => (string) ($current - 2),
                            ];
                        })
                        ->default(now()->year)
                        ->required(),

                    Forms\Components\Select::make('quarter')
                        ->label('Trimestre')
                        ->options([
                            1 => '1º Trimestre (01/01 a 31/03)',
                            2 => '2º Trimestre (01/04 a 30/06)',
                            3 => '3º Trimestre (01/07 a 30/09)',
                            4 => '4º Trimestre (01/10 a 31/12)',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(QuarterlyClosingService::class);

                    try {
                        $service->closeQuarter((int) $data['year'], (int) $data['quarter'], auth()->user());

                        Notification::make()
                            ->title('Trimestre fechado com sucesso!')
                            ->body('O relatório oficial foi gerado e o período está travado contra alterações.')
                            ->success()
                            ->send();
                    } catch (QuarterlyClosingException $e) {
                        Notification::make()
                            ->title('Não foi possível fechar o trimestre')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
