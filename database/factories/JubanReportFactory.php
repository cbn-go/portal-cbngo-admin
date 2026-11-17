<?php

namespace Database\Factories;

use App\Enums\JubanReportStatus;
use App\Models\JubanReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JubanReport>
 */
class JubanReportFactory extends Factory
{
    protected $model = JubanReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2020, 2025);
        $quarter = fake()->numberBetween(1, 4);

        $periodFrom = match ($quarter) {
            1 => "{$year}-01-01",
            2 => "{$year}-04-01",
            3 => "{$year}-07-01",
            default => "{$year}-10-01",
        };

        $periodUntil = match ($quarter) {
            1 => "{$year}-03-31",
            2 => "{$year}-06-30",
            3 => "{$year}-09-30",
            default => "{$year}-12-31",
        };

        return [
            'year' => $year,
            'quarter' => $quarter,
            'period_from' => $periodFrom,
            'period_until' => $periodUntil,
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
            'snapshot' => [
                'metadata' => [
                    'generated_at' => now()->toIso8601String(),
                ],
                'summary' => [
                    'totalReceitas' => 10000.00,
                    'totalDespesas' => 4500.00,
                    'saldo' => 5500.00,
                    'total_transactions' => 15,
                ],
                'categories' => [],
                'monthly_despesas' => [],
                'transactions' => [],
            ],
            'pdf_path' => "juban-reports/{$year}-q{$quarter}-relatorio.pdf",
            'pdf_sha256' => hash('sha256', "dummy-pdf-content-{$year}-{$quarter}"),
            'closed_by' => User::factory(),
            'closed_at' => Carbon::parse($periodUntil)->addDay(),
            'reopened_by' => null,
            'reopened_at' => null,
            'reopen_reason' => null,
        ];
    }

    public function assinado(): static
    {
        return $this->state(fn () => [
            'status' => JubanReportStatus::ASSINADO,
        ]);
    }

    public function reaberto(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JubanReportStatus::REABERTO,
            'pdf_path' => null,
            'pdf_sha256' => null,
            'reopened_by' => User::factory(),
            'reopened_at' => now(),
            'reopen_reason' => 'Necessidade de ajuste em lançamento de evento.',
        ]);
    }
}
