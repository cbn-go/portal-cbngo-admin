<?php

namespace App\Filament\Ormiban\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Strategy\GoiasOverview;
use App\Filament\Resources\SocialProjectResource;
use App\Models\Church;
use App\Models\Minister;
use App\Models\User;
use App\Support\PanelGreeting;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Tela inicial do painel Ormiban: quem o ministro é no rol e qual o próximo passo.
 * Mostra só a ficha do próprio usuário; nada de totais da convenção nem valores financeiros.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Início';

    protected static ?string $title = 'Início';

    protected static string $view = 'filament.ormiban.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return PanelGreeting::greeting($this->user()?->name, 'Ministro');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return PanelGreeting::today();
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function minister(): ?Minister
    {
        return $this->user()?->minister;
    }

    /**
     * Mesma regra de Minha Igreja: a igreja da ficha ou, na falta dela, a do usuário.
     */
    public function church(): ?Church
    {
        $user = $this->user();

        return $user?->minister?->church ?? $user?->church;
    }

    public function isSuperAdminWithoutMinister(): bool
    {
        return $this->user()?->role === UserRole::SUPER_ADMIN && $this->minister() === null;
    }

    /**
     * Só o ministro com ficha e sem igreja pode vincular ou cadastrar a própria igreja.
     */
    public function canClaimChurch(): bool
    {
        return $this->user()?->role === UserRole::MINISTER
            && $this->minister() !== null
            && $this->church() === null;
    }

    /**
     * @return array{function: string, registration_number: string, status: string, is_active: bool, church: string, ministry_time: string}|null
     */
    public function summary(): ?array
    {
        $minister = $this->minister();

        if ($minister === null) {
            return null;
        }

        return [
            'function' => filled($minister->function) ? $minister->function : 'Não informada',
            'registration_number' => filled($minister->registration_number) ? $minister->registration_number : 'Não informada',
            'status' => filled($minister->status) ? $minister->status : 'Não informada',
            'is_active' => $minister->isActiveOnRoll(),
            'church' => EditMinisterProfile::churchLabel($minister),
            'ministry_time' => EditMinisterProfile::ministryTimeLabel($minister),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string}>
     */
    public function shortcuts(): array
    {
        $shortcuts = [
            [
                'label' => 'Meu Perfil',
                'description' => 'Contato, endereço e senha',
                'icon' => 'heroicon-o-user-circle',
                'url' => EditMinisterProfile::getUrl(),
                'visible' => EditMinisterProfile::canAccess(),
            ],
            [
                'label' => 'Minha Igreja',
                'description' => 'Dados e horários da congregação',
                'icon' => 'heroicon-o-building-office-2',
                'url' => EditMyChurch::getUrl(),
                'visible' => EditMyChurch::canAccess(),
            ],
            [
                'label' => 'Projetos Sociais',
                'description' => 'Ações sociais das igrejas',
                'icon' => 'heroicon-o-heart',
                'url' => SocialProjectResource::getUrl('index'),
                'visible' => SocialProjectResource::canAccess(),
            ],
            [
                'label' => 'Visão de Goiás',
                'description' => 'Municípios e presença da CBN-GO',
                'icon' => 'heroicon-o-map',
                'url' => GoiasOverview::getUrl(),
                'visible' => GoiasOverview::canAccess(),
            ],
        ];

        return array_values(array_map(
            fn (array $shortcut): array => array_diff_key($shortcut, ['visible' => true]),
            array_filter($shortcuts, fn (array $shortcut): bool => $shortcut['visible']),
        ));
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
