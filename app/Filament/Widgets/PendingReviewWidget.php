<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\SocialProjectResource;
use App\Models\Article;
use App\Models\News;
use App\Models\SocialProject;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PendingReviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.pending-review-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAdminPanelAccess() ?? false;
    }

    /**
     * @return Collection<int, array{type: string, type_color: string, title: string, author: string, created_at: Carbon|null, url: string}>
     */
    public function getPendingItems(): Collection
    {
        $news = News::query()
            ->with('user')
            ->where('status', PublishStatus::PENDING_REVIEW)
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (News $item): array => [
                'type' => 'Notícia',
                'type_color' => 'info',
                'title' => $item->title,
                'author' => $item->user?->name ?? 'Redação',
                'created_at' => $item->created_at,
                'url' => NewsResource::getUrl('edit', ['record' => $item]),
            ]);

        $articles = Article::query()
            ->with('user')
            ->where('status', PublishStatus::PENDING_REVIEW)
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (Article $item): array => [
                'type' => 'Artigo',
                'type_color' => 'success',
                'title' => $item->title,
                'author' => $item->user?->name ?? 'Autor',
                'created_at' => $item->created_at,
                'url' => ArticleResource::getUrl('edit', ['record' => $item]),
            ]);

        $projects = SocialProject::query()
            ->with('church')
            ->where('status', PublishStatus::PENDING_REVIEW)
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (SocialProject $item): array => [
                'type' => 'Projeto Social',
                'type_color' => 'warning',
                'title' => (string) $item->title,
                'author' => $item->church?->name ?? 'Igreja',
                'created_at' => $item->created_at,
                'url' => SocialProjectResource::getUrl('edit', ['record' => $item]),
            ]);

        return $news
            ->concat($articles)
            ->concat($projects)
            ->sortBy('created_at')
            ->take(5)
            ->values();
    }
}
