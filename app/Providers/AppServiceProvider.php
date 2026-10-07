<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\News;
use App\Models\Notice;
use App\Policies\ArticlePolicy;
use App\Policies\NewsPolicy;
use App\Policies\NoticePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(Notice::class, NoticePolicy::class);
    }
}
