<?php

namespace Tests\Feature\Filament\Strategy;

use App\Filament\Strategy\Widgets\AgeGroupChartWidget;
use App\Filament\Strategy\Widgets\MunicipalityMapWidget;
use App\Filament\Strategy\Widgets\StrategyKpisWidget;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\ComponentRegistry;
use Tests\TestCase;

/**
 * Os widgets da Visão de Goiás ficam fora da pasta descoberta pelo Filament.
 * Sem registro no Livewire, toda atualização após a primeira renderização
 * volta 419 ("Page Expired") em produção.
 */
class StrategyWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    private function widgets(): array
    {
        return [StrategyKpisWidget::class, MunicipalityMapWidget::class, AgeGroupChartWidget::class];
    }

    public function test_widgets_are_registered_with_livewire(): void
    {
        $registry = app(ComponentRegistry::class);

        foreach ($this->widgets() as $widget) {
            $this->assertSame($widget, $registry->getClass($registry->getName($widget)));
        }
    }

    public function test_widgets_do_not_poll(): void
    {
        // Os dados mudam só na sincronização mensal; polling a cada 5 s não traz nada novo.
        foreach ($this->widgets() as $widget) {
            // O mapa é um widget simples, sem polling nenhum.
            if (! method_exists($widget, 'getPollingInterval')) {
                continue;
            }

            $method = new \ReflectionMethod($widget, 'getPollingInterval');

            $this->assertNull($method->invoke(new $widget));
        }
    }

    public function test_widgets_do_not_appear_on_the_dashboard(): void
    {
        foreach ($this->widgets() as $widget) {
            $this->assertNotContains($widget, Filament::getPanel('admin')->getWidgets());
        }
    }
}
