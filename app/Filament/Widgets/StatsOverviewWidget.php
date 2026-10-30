<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Church;
use App\Models\News;
use App\Models\SocialProject;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $fetchStats = function (): array {
            $activeChurches = Church::query()->where('is_active', true)->count();
            $citiesCount = Church::query()->where('is_active', true)->distinct('city')->count('city');

            $pendingNews = News::query()->where('status', PublishStatus::PENDING_REVIEW)->count();
            $pendingArticles = Article::query()->where('status', PublishStatus::PENDING_REVIEW)->count();
            $pendingProjects = SocialProject::query()->where('status', PublishStatus::PENDING_REVIEW)->count();
            $totalPending = $pendingNews + $pendingArticles + $pendingProjects;

            $publishedNews = News::query()->where('status', PublishStatus::PUBLISHED)->count();
            $publishedArticles = Article::query()->where('status', PublishStatus::PUBLISHED)->count();
            $totalPublished = $publishedNews + $publishedArticles;

            return [
                'active_churches' => $activeChurches,
                'cities_count' => $citiesCount,
                'total_pending' => $totalPending,
                'total_published' => $totalPublished,
            ];
        };

        $stats = app()->environment('testing')
            ? $fetchStats()
            : Cache::remember('cbngo:dashboard:stats:overview', 300, $fetchStats);

        $citiesLabel = $stats['cities_count'] === 1 ? '1 cidade no estado de Goiás' : "{$stats['cities_count']} cidades no estado de Goiás";

        return [
            Stat::make('Igrejas Ativas', (string) $stats['active_churches'])
                ->description($citiesLabel)
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),

            Stat::make('Publicações Pendentes', (string) $stats['total_pending'])
                ->description($stats['total_pending'] > 0 ? 'Aguardando revisão editorial' : 'Tudo em dia')
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats['total_pending'] > 0 ? 'warning' : 'success'),

            Stat::make('No Ar no Portal', (string) $stats['total_published'])
                ->description('Notícias e artigos publicados')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('gray'),
        ];
    }
}
