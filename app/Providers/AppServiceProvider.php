<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\ChurchPolicy;
use App\Policies\NewsPolicy;
use App\Policies\NoticePolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Church::class, ChurchPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(Notice::class, NoticePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
