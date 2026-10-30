<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de mídia
    |--------------------------------------------------------------------------
    |
    | Disco onde o painel grava todos os uploads consumidos pelo portal
    | público. O banco guarda apenas o caminho relativo a este disco.
    |
    */

    'disk' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Versões otimizadas de imagem
    |--------------------------------------------------------------------------
    |
    | Cada imagem enviada ganha uma versão WebP por tamanho, gravada ao lado
    | do original como "{nome}-{tamanho}.webp". A largura nunca é ampliada.
    | O original é guardado como foi enviado: todo corte e redução acontece
    | aqui, no servidor, para não perder qualidade no navegador.
    |
    */

    'variants' => [
        'thumb' => 400,
        'card' => 800,
        'cover' => 1600,
        'full' => 2400, // telas de alta densidade (celular, Mac)
    ],

    'banner_variants' => [
        'thumb' => 160,
        'card' => 800,
        'cover' => 1080,
    ],

    // Proporção das versões por diretório (prefixo do caminho). O corte é
    // centralizado; diretórios fora da lista mantêm a proporção do original.
    'aspect_ratios' => [
        'news/featured/' => [16, 9],
        'articles/covers/' => [16, 9],
        'projects/covers/' => [16, 9],
    ],

    'webp_quality' => 85,

    // Fotos de celular ocupam centenas de MB quando abertas pelo GD.
    'memory_limit' => '512M',

];
