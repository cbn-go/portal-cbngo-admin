<?php

namespace App\Filament\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Widgets\PendingReviewWidget;
use App\Filament\Widgets\PortalSummaryWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Visão Geral';

    public function getTitle(): string|Htmlable
    {
        $hour = (int) Carbon::now('America/Sao_Paulo')->format('H');

        $greeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Bom dia',
            $hour >= 12 && $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };

        $fullName = auth()->user()?->name ?? 'Administrador';
        $firstName = Str::of($fullName)->trim()->explode(' ')->first();

        return "{$greeting}, {$firstName}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        $date = Carbon::now('America/Sao_Paulo')
            ->locale('pt_BR')
            ->translatedFormat('l, d \d\e F \d\e Y');

        return ucfirst($date);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_news')
                ->label('Nova notícia')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(fn (): string => NewsResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->hasAdminPanelAccess() ?? false),
        ];
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            PendingReviewWidget::class,
            PortalSummaryWidget::class,
        ];
    }
}
