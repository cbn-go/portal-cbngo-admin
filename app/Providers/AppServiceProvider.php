<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Banner;
use App\Models\BoardMember;
use App\Models\Church;
use App\Models\Document;
use App\Models\Municipality;
use App\Models\News;
use App\Models\Notice;
use App\Models\SocialProject;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\BannerPolicy;
use App\Policies\BoardMemberPolicy;
use App\Policies\ChurchPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\MunicipalityPolicy;
use App\Policies\NewsPolicy;
use App\Policies\NoticePolicy;
use App\Policies\SocialProjectPolicy;
use App\Policies\UserPolicy;
use App\Services\Strategy\StateIndicatorService;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
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

        // Indicadores lidos uma vez por requisição nas telas do Painel de Estratégia.
        $this->app->scoped(StateIndicatorService::class);
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
        Gate::policy(Municipality::class, MunicipalityPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(Notice::class, NoticePolicy::class);
        Gate::policy(SocialProject::class, SocialProjectPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Mapa do Painel de Estratégia: Leaflet versionado no repositório e
        // carregado só na Visão de Goiás. Publicado em public/ com `filament:assets`.
        FilamentAsset::register([
            Css::make('leaflet', resource_path('vendor/leaflet/leaflet.css'))->loadedOnRequest(),
            Js::make('leaflet', resource_path('vendor/leaflet/leaflet.js'))->loadedOnRequest(),
            AlpineComponent::make('strategy-map', resource_path('js/filament/strategy-map.js')),
        ]);
    }
}
