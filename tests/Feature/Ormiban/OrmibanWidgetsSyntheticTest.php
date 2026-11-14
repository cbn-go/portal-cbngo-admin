<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Filament\Ormiban\Widgets\AgeChartWidget;
use App\Filament\Ormiban\Widgets\MinistryAgeChartWidget;
use App\Filament\Ormiban\Widgets\OrdinationsByYearChartWidget;
use App\Models\Minister;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class OrmibanWidgetsSyntheticTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04', 'America/Sao_Paulo'));

        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function invokeGetData(object $widget): array
    {
        $ref = new ReflectionMethod($widget, 'getData');

        /** @var array{datasets: array<int, array{data: array<int, int>, label: string}>, labels: array<int, string>} $result */
        $result = $ref->invoke($widget);

        return $result;
    }

    public function test_age_chart_widget_with_synthetic_dataset(): void
    {
        // 1. Alfa: 71 anos (faixa '70 ou mais')
        Minister::factory()->create([
            'function' => 'Pastor',
            'birth_date' => '1955-05-15',
            'status' => Minister::STATUS_ACTIVE,
        ]);

        // 2. Beta: 48 anos (faixa '40–49')
        Minister::factory()->create([
            'function' => 'Pastor',
            'birth_date' => '1978-08-20',
            'status' => Minister::STATUS_ACTIVE,
        ]);

        // 3. Gama: 32 anos (faixa '30–39')
        Minister::factory()->create([
            'function' => 'Missionário',
            'birth_date' => '1993-12-10',
            'status' => Minister::STATUS_ACTIVE,
        ]);

        // 4. Delta: 26 anos (faixa 'Menos de 30')
        Minister::factory()->create([
            'function' => 'Pastor',
            'birth_date' => '2000-01-01',
            'status' => Minister::STATUS_ACTIVE,
        ]);

        // 5. Epsilon: Sem data de nascimento (não entra em ageBands)
        Minister::factory()->create([
            'function' => 'Pastor',
            'birth_date' => null,
            'status' => Minister::STATUS_ACTIVE,
        ]);

        $testable = Livewire::test(AgeChartWidget::class);
        $widget = $testable->instance();
        $chartData = $this->invokeGetData($widget);

        $expectedLabels = ['Menos de 30', '30–39', '40–49', '50–59', '60–69', '70 ou mais'];
        $this->assertSame($expectedLabels, $chartData['labels']);

        // Data map:
        // 'Menos de 30' => 1 (Delta)
        // '30–39' => 1 (Gama)
        // '40–49' => 1 (Beta)
        // '50–59' => 0
        // '60–69' => 0
        // '70 ou mais' => 1 (Alfa)
        $expectedCounts = [1, 1, 1, 0, 0, 1];
        $this->assertSame($expectedCounts, $chartData['datasets'][0]['data']);

        // Test with function filter = 'Missionário'
        $filteredWidget = Livewire::test(AgeChartWidget::class, [
            'filters' => ['function' => 'Missionário'],
        ])->instance();
        $filteredData = $this->invokeGetData($filteredWidget);
        $this->assertSame([0, 1, 0, 0, 0, 0], $filteredData['datasets'][0]['data']);
    }

    public function test_ministry_age_chart_widget_with_synthetic_dataset(): void
    {
        // 1. Alfa: Ordenado em 1985-06-10 (41 anos de ordenação -> faixa '40–49')
        $alfa = Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $alfa->historyEvents()->create([
            'occurred_on' => '1985-06-10',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        // 2. Beta: Ordenado em 2010-09-15 (16 anos de ordenação -> faixa 'Menos de 30')
        $beta = Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $beta->historyEvents()->create([
            'occurred_on' => '2010-09-15',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        // 3. Gama: Ordenado em 2023-01-20 (3 anos de ordenação -> faixa 'Menos de 30')
        $gama = Minister::factory()->create([
            'function' => 'Missionário',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $gama->historyEvents()->create([
            'occurred_on' => '2023-01-20',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        // 4. Delta: Sem registro de ordenação -> 'Sem ordenação'
        Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
        ]);

        // 5. Epsilon: Ordenado em 2023-05-10 (3 anos de ordenação -> faixa 'Menos de 30')
        $epsilon = Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $epsilon->historyEvents()->create([
            'occurred_on' => '2023-05-10',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        $testable = Livewire::test(MinistryAgeChartWidget::class);
        $widget = $testable->instance();
        $chartData = $this->invokeGetData($widget);

        $expectedLabels = ['Menos de 30', '30–39', '40–49', '50–59', '60–69', '70 ou mais', 'Sem ordenação'];
        $this->assertSame($expectedLabels, $chartData['labels']);

        // Data map:
        // 'Menos de 30' => 3 (Beta, Gama, Epsilon)
        // '30–39' => 0
        // '40–49' => 1 (Alfa)
        // '50–59' => 0
        // '60–69' => 0
        // '70 ou mais' => 0
        // 'Sem ordenação' => 1 (Delta)
        $expectedCounts = [3, 0, 1, 0, 0, 0, 1];
        $this->assertSame($expectedCounts, $chartData['datasets'][0]['data']);
    }

    public function test_ordinations_by_year_chart_widget_with_synthetic_dataset(): void
    {
        // Cria ordenações em anos conhecidos: 2020, 2022 e 2023
        $pastorA = Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $pastorA->historyEvents()->create([
            'occurred_on' => '2020-04-12',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        $pastorB = Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $pastorB->historyEvents()->create([
            'occurred_on' => '2022-07-15',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        $missionaryC = Minister::factory()->create([
            'function' => 'Missionário',
            'status' => Minister::STATUS_ACTIVE,
        ]);
        $missionaryC->historyEvents()->create([
            'occurred_on' => '2023-10-01',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        $testable = Livewire::test(OrdinationsByYearChartWidget::class);
        $widget = $testable->instance();
        $chartData = $this->invokeGetData($widget);

        // Anos entre o primeiro ano com ordenação (2020) e o ano corrente (2026) preenchidos consecutivamente
        $expectedYears = [2020, 2021, 2022, 2023, 2024, 2025, 2026];
        $this->assertSame($expectedYears, $chartData['labels']);

        // Contagens: 2020: 1, 2021: 0, 2022: 1, 2023: 1, 2024..2026: 0
        $expectedCounts = [1, 0, 1, 1, 0, 0, 0];
        $this->assertSame($expectedCounts, $chartData['datasets'][0]['data']);

        // Filtrado por 'Missionário' deve conter os anos a partir da ordenação de 2023 até o ano corrente (2026)
        $filteredWidget = Livewire::test(OrdinationsByYearChartWidget::class, [
            'filters' => ['function' => 'Missionário'],
        ])->instance();
        $filteredData = $this->invokeGetData($filteredWidget);
        $this->assertSame([2023, 2024, 2025, 2026], $filteredData['labels']);
        $this->assertSame([1, 0, 0, 0], $filteredData['datasets'][0]['data']);
    }
}
