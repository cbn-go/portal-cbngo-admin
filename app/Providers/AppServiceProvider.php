<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Banner;
use App\Models\BoardMember;
use App\Models\Church;
use App\Models\Document;
use App\Models\News;
use App\Models\Notice;
use App\Models\SocialProject;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\BannerPolicy;
use App\Policies\BoardMemberPolicy;
use App\Policies\ChurchPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\NewsPolicy;
use App\Policies\NoticePolicy;
use App\Policies\SocialProjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $publicHtml = base_path('public_html');

        if (is_dir($publicHtml)) {
            $this->app->usePublicPath($publicHtml);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Banner::class, BannerPolicy::class);
        Gate::policy(BoardMember::class, BoardMemberPolicy::class);
        Gate::policy(Church::class, ChurchPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(Notice::class, NoticePolicy::class);
        Gate::policy(SocialProject::class, SocialProjectPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
