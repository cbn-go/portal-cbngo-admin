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
    |
    */

    'variants' => [
        'thumb' => 400,
        'card' => 800,
        'cover' => 1600,
    ],

    'banner_variants' => [
        'thumb' => 160,
        'card' => 800,
        'cover' => 1080,
    ],

    'webp_quality' => 80,

];
