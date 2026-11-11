<?php

namespace Database\Seeders;

use App\Enums\JubanCategoryKind;
use App\Models\JubanCategory;
use Illuminate\Database\Seeder;

class JubanCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Receitas
            [
                'code' => 'inscricoes-eventos',
                'name' => 'Inscrições de Eventos',
                'kind' => JubanCategoryKind::RECEITA,
                'description' => 'Taxas e inscrições de acampamentos, congressos e retiros JUBAN.',
            ],
            [
                'code' => 'ofertas-doacoes',
                'name' => 'Ofertas e Doações',
                'kind' => JubanCategoryKind::RECEITA,
                'description' => 'Ofertas voluntárias recolhidas em cultos e eventos de jovens.',
            ],
            [
                'code' => 'venda-materiais',
                'name' => 'Venda de Materiais e Camisetas',
                'kind' => JubanCategoryKind::RECEITA,
                'description' => 'Venda de camisetas, livros, brindes e itens temáticos da juventude.',
            ],
            [
                'code' => 'repasse-convencao',
                'name' => 'Repasse da Convenção',
                'kind' => JubanCategoryKind::RECEITA,
                'description' => 'Recursos repassados pela CBN Goiás para a liderança da juventude.',
            ],
            [
                'code' => 'outras-receitas',
                'name' => 'Outras Receitas',
                'kind' => JubanCategoryKind::RECEITA,
                'description' => 'Entradas diversas e receitas extraordinárias.',
            ],

            // Despesas
            [
                'code' => 'alimentacao',
                'name' => 'Alimentação e Coffee Break',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Alimentação, lanches e refeições em reuniões, vigílias e congressos.',
            ],
            [
                'code' => 'hospedagem-transporte',
                'name' => 'Hospedagem e Transporte',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Combustível, passagens, vans, fretes e hospedagens de equipes.',
            ],
            [
                'code' => 'preletores-musicos',
                'name' => 'Preletores e Músicos',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Honorários, ofertas de gratidão e acolhimento de preletores e convidados.',
            ],
            [
                'code' => 'som-multimidia',
                'name' => 'Som, Iluminação e Multimídia',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Locação e manutenção de equipamentos audiovisuais.',
            ],
            [
                'code' => 'grafica-brindes',
                'name' => 'Gráfica, Camisetas e Brindes',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Custo de confecção de crachás, banners, folders e camisetas.',
            ],
            [
                'code' => 'material-administrativo',
                'name' => 'Material Administrativo',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Materiais de escritório, expediente e correios.',
            ],
            [
                'code' => 'taxas-bancarias',
                'name' => 'Taxas e Tarifas Bancárias',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Tarifas de manutenção de conta, transferências, boletos e maquinetas.',
            ],
            [
                'code' => 'outras-despesas',
                'name' => 'Outras Despesas',
                'kind' => JubanCategoryKind::DESPESA,
                'description' => 'Gastos eventuais e imprevistos autorizados pela diretoria.',
            ],

            // Ambos
            [
                'code' => 'ajuste-saldo',
                'name' => 'Ajuste de Saldo',
                'kind' => JubanCategoryKind::AMBOS,
                'description' => 'Lançamento de abertura de caixa ou ajuste de conciliação contábil.',
            ],
        ];

        foreach ($categories as $data) {
            JubanCategory::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'kind' => $data['kind'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
