<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Banner;
use App\Models\News;
use App\Models\Notice;
use Filament\Widgets\Widget;

class PortalSummaryWidget extends Widget
{
    protected static string $view = 'filament.widgets.portal-summary-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $visibleBannersCount = Banner::query()->visible()->count();
        $nextScheduledBanner = Banner::query()->scheduled()->first();

        $activeNoticesCount = Notice::query()->currentlyActive()->count();

        $publishedNewsCount = News::query()->where('status', PublishStatus::PUBLISHED)->count();
        $publishedArticlesCount = Article::query()->where('status', PublishStatus::PUBLISHED)->count();

        return [
            'visible_banners_count' => $visibleBannersCount,
            'next_scheduled_banner' => $nextScheduledBanner,
            'active_notices_count' => $activeNoticesCount,
            'published_news_count' => $publishedNewsCount,
            'published_articles_count' => $publishedArticlesCount,
            'site_url' => config('portal.site_url', 'https://cbngo.org.br'),
        ];
    }
}
