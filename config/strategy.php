<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Painel de Estratégia: Índice de Prioridade Municipal
    |--------------------------------------------------------------------------
    |
    | O índice de prioridade municipal (0 a 100) apoia a conversa e o
    | direcionamento estratégico da diretoria da CBN-GO para plantação e
    | apoio de igrejas nos 246 municípios de Goiás.
    |
    | Os pesos são balanceados e somam 1.00 (100%).
    |
    */
    'priority_index' => [
        'weights' => [
            'population' => 0.30,          // População estimada mais recente
            'growth' => 0.20,              // Taxa de crescimento populacional (%)
            'non_evangelical_pct' => 0.25, // Percentual estimado de não evangélicos (%)
            'church_absence' => 0.25,      // Ausência/carência de igrejas da CBN-GO
        ],

        // Modelo de decaimento do score de ausência conforme número de igrejas ativas:
        // 0 igrejas: 100.0, 1 igreja: 50.0, 2 igrejas: 33.33, n igrejas: 100 / (1 + n)
        'church_absence_decay' => 'inverse_proportional',

        // Estratégia para tratar municípios sem dados no IBGE:
        // 'reweight_available' redistribui proporcionalmente o peso entre os indicadores disponíveis.
        'missing_data_strategy' => 'reweight_available',

        // Metadados para documentação metodológica no painel
        'metadata' => [
            'population' => [
                'label' => 'População Total',
                'description' => 'População estimada mais recente ou do Censo 2022 (IBGE).',
                'source' => 'IBGE Cidades / SIDRA 6579 / 4714',
            ],
            'growth' => [
                'label' => 'Crescimento Populacional',
                'description' => 'Variação percentual entre o Censo 2022 e a estimativa mais recente.',
                'source' => 'Cálculo derivado sobre dados do IBGE',
            ],
            'non_evangelical_pct' => [
                'label' => '% Não Evangélicos',
                'description' => 'Proporção estimada de residentes não evangélicos no município.',
                'source' => 'Censo Demográfico 2022 (SIDRA 9537)',
            ],
            'church_absence' => [
                'label' => 'Ausência de Igreja',
                'description' => 'Carência de templos da CBN-GO (máxima pontuação para municípios sem igreja ativa).',
                'source' => 'Cadastro interno de Igrejas CBN-GO',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapa: malha municipal de Goiás
    |--------------------------------------------------------------------------
    |
    | GeoJSON baixado da API de Malhas do IBGE pelo comando strategy:sync-map
    | e servido pelo painel em /admin/estrategia/malha.geojson.
    |
    */
    'map_path' => storage_path('app/strategy/goias-municipios.geojson'),
];
