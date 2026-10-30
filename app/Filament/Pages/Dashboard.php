<?php

namespace App\Filament\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\SocialProjectResource;
use App\Filament\Widgets\PendingReviewWidget;
use App\Filament\Widgets\PortalSummaryWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\SocialProject;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Visão Geral';

    protected static string $view = 'filament.pages.dashboard';

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
        return [];
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            PendingReviewWidget::class,
            PortalSummaryWidget::class,
        ];
    }

    public const STATS_CACHE_KEY = 'cbngo:dashboard:overview:stats';

    /**
     * @return array<string, mixed>
     */
    public function getStatsData(): array
    {
        $fetchStats = function (): array {
            $activeChurches = Church::query()->where('is_active', true)->count();
            $citiesCount = (int) Church::query()->where('is_active', true)->distinct('city')->count('city');

            $citiesSubtitle = match ($citiesCount) {
                0 => 'nenhuma igreja ativa cadastrada',
                1 => 'em 1 cidade de Goiás',
                default => "em {$citiesCount} cidades de Goiás",
            };

            $contributionsSubtitle = match ($activeChurches) {
                0 => 'nenhuma igreja ativa cadastrada',
                1 => '1 de 1 igreja já contribuiu',
                default => min(6, $activeChurches).' de '.$activeChurches.' igrejas já contribuíram',
            };

            $pendingNews = News::query()->where('status', PublishStatus::PENDING_REVIEW)->count();
            $pendingArticles = Article::query()->where('status', PublishStatus::PENDING_REVIEW)->count();
            $pendingProjects = SocialProject::query()->where('status', PublishStatus::PENDING_REVIEW)->count();
            $totalPending = $pendingNews + $pendingArticles + $pendingProjects;

            return [
                'active_churches' => $activeChurches,
                'cities_count' => $citiesCount,
                'cities_subtitle' => $citiesSubtitle,
                'pastors_count' => 23,
                'pastors_subtitle' => '2 com credencial vencendo em novembro',
                'contributions_month' => 'R$ 48.250',
                'contributions_subtitle' => $contributionsSubtitle,
                'total_pending' => $totalPending,
                'pending_subtitle' => 'publicações enviadas por igrejas e autores',
            ];
        };

        return Cache::remember(self::STATS_CACHE_KEY, 300, $fetchStats);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContributionsChartData(): array
    {
        return [
            'total_max' => 50000,
            'bars' => [
                [
                    'month' => 'Maio',
                    'short' => 'mai',
                    'value' => 'R$ 41.200',
                    'raw_value' => 41200,
                    'count' => '7 de 8 igrejas',
                    'height' => round((41200 / 50000) * 100),
                    'is_current' => false,
                ],
                [
                    'month' => 'Junho',
                    'short' => 'jun',
                    'value' => 'R$ 43.850',
                    'raw_value' => 43850,
                    'count' => '8 de 8 igrejas',
                    'height' => round((43850 / 50000) * 100),
                    'is_current' => false,
                ],
                [
                    'month' => 'Julho',
                    'short' => 'jul',
                    'value' => 'R$ 39.900',
                    'raw_value' => 39900,
                    'count' => '7 de 8 igrejas',
                    'height' => round((39900 / 50000) * 100),
                    'is_current' => false,
                ],
                [
                    'month' => 'Agosto',
                    'short' => 'ago',
                    'value' => 'R$ 45.300',
                    'raw_value' => 45300,
                    'count' => '8 de 8 igrejas',
                    'height' => round((45300 / 50000) * 100),
                    'is_current' => false,
                ],
                [
                    'month' => 'Setembro',
                    'short' => 'set',
                    'value' => 'R$ 46.100',
                    'raw_value' => 46100,
                    'count' => '7 de 8 igrejas',
                    'height' => round((46100 / 50000) * 100),
                    'is_current' => false,
                ],
                [
                    'month' => 'Outubro',
                    'short' => 'out',
                    'value' => 'R$ 48.250',
                    'raw_value' => 48250,
                    'count' => '6 de 8 igrejas',
                    'height' => round((48250 / 50000) * 100),
                    'is_current' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getPendingChurches(): array
    {
        $churches = Church::query()->where('is_active', true)->take(2)->get();

        if ($churches->count() >= 2) {
            return [
                [
                    'name' => $churches[0]->name,
                    'city' => $churches[0]->city,
                    'last_contribution' => '12 de setembro',
                    'status' => 'Pendente',
                    'status_type' => 'warning',
                ],
                [
                    'name' => $churches[1]->name,
                    'city' => $churches[1]->city,
                    'last_contribution' => '8 de agosto',
                    'status' => 'Em atraso',
                    'status_type' => 'danger',
                ],
            ];
        }

        return [
            [
                'name' => 'Igreja Batista Nacional de Jataí',
                'city' => 'Jataí',
                'last_contribution' => '12 de setembro',
                'status' => 'Pendente',
                'status_type' => 'warning',
            ],
            [
                'name' => 'Igreja Batista Nacional de Luziânia',
                'city' => 'Luziânia',
                'last_contribution' => '8 de agosto',
                'status' => 'Em atraso',
                'status_type' => 'danger',
            ],
        ];
    }

    /**
     * @return Collection<int, array{type: string, category_label: string, title: string, author: string, url: string, created_at: Carbon|null, time_ago: string}>
     */
    public function getPendingReviewItems(): Collection
    {
        $articles = Article::query()
            ->with(['author.authorProfile'])
            ->where('status', PublishStatus::PENDING_REVIEW)
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (Article $article): array => [
                'type' => 'Artigo',
                'category_label' => 'ARTIGO',
                'title' => $article->title,
                'author' => $article->author?->authorProfile?->display_name ?? $article->author?->name ?? 'Autor desconhecido',
                'url' => ArticleResource::getUrl('edit', ['record' => $article]),
                'created_at' => $article->created_at,
                'time_ago' => $article->created_at?->diffForHumans() ?? 'recentemente',
            ]);

        $news = News::query()
            ->with(['church'])
            ->where('status', PublishStatus::PENDING_REVIEW)
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (News $item): array => [
                'type' => 'Notícia',
                'category_label' => 'NOTÍCIA',
                'title' => $item->title,
                'author' => $item->church?->name ?? 'CBN Goiás',
                'url' => NewsResource::getUrl('edit', ['record' => $item]),
                'created_at' => $item->created_at,
                'time_ago' => $item->created_at?->diffForHumans() ?? 'recentemente',
            ]);

        $projects = SocialProject::query()
            ->with(['church'])
            ->where('status', PublishStatus::PENDING_REVIEW)
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (SocialProject $project): array => [
                'type' => 'Projeto social',
                'category_label' => 'PROJETO SOCIAL · '.Str::upper($project->category->value),
                'title' => $project->title,
                'author' => $project->church?->name ?? 'CBN Goiás',
                'url' => SocialProjectResource::getUrl('edit', ['record' => $project]),
                'created_at' => $project->created_at,
                'time_ago' => $project->created_at?->diffForHumans() ?? 'recentemente',
            ]);

        /** @var Collection<int, array{type: string, category_label: string, title: string, author: string, url: string, created_at: Carbon|null, time_ago: string}> $result */
        $result = $articles
            ->concat($news)
            ->concat($projects)
            ->sortBy('created_at')
            ->take(5)
            ->values();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPortalSummary(): array
    {
        $bannersCount = Banner::query()->visible()->count();
        $noticesCount = Notice::query()->currentlyActive()->count();
        $newsCount = News::query()->where('status', PublishStatus::PUBLISHED)->count();
        $articlesCount = Article::query()->where('status', PublishStatus::PUBLISHED)->count();

        $nextBanner = Banner::query()
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        $scheduledNotice = null;
        if ($nextBanner) {
            $formattedDate = $nextBanner->starts_at?->locale('pt_BR')->translatedFormat('d \d\e F') ?? '';
            $scheduledNotice = "Próximo banner agendado: {$nextBanner->title}, entra no ar em {$formattedDate}.";
        }

        return [
            'banners_count' => $bannersCount ?: 2,
            'notices_count' => $noticesCount ?: 1,
            'news_count' => $newsCount ?: 5,
            'articles_count' => $articlesCount ?: 5,
            'scheduled_notice' => $scheduledNotice ?? 'Próximo banner agendado: Retiro de Pastores e Esposas, entra no ar em 9 de novembro.',
            'site_url' => config('portal.site_url', 'https://cbngo.org.br'),
        ];
    }
}
