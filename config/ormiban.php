<?php

$bands = [
    ['label' => 'Menos de 30', 'max' => 29],
    ['label' => '30–39', 'min' => 30, 'max' => 39],
    ['label' => '40–49', 'min' => 40, 'max' => 49],
    ['label' => '50–59', 'min' => 50, 'max' => 59],
    ['label' => '60–69', 'min' => 60, 'max' => 69],
    ['label' => '70 ou mais', 'min' => 70],
];

return [
    /*
    |--------------------------------------------------------------------------
    | Faixas dos indicadores do rol Ormiban
    |--------------------------------------------------------------------------
    |
    | Anos completos até a data de hoje, no fuso da aplicação
    | (padrão America/Sao_Paulo). Cada lista pode ser ajustada
    | sem mudar as outras. min e max são inclusivos.
    |
    */
    'age_bands' => $bands,
    'ministry_bands' => $bands,
    'ordination_bands' => $bands,
    'missing_diploma_label' => 'Sem diplomação',
    'missing_ordination_label' => 'Sem ordenação',
];
