<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domínios do portal público
    |--------------------------------------------------------------------------
    |
    | Links para estes domínios (ou seus subdomínios) são tratados como
    | internos: abrem na mesma aba do portal público. Qualquer outro domínio
    | é externo.
    |
    */

    'internal_hosts' => [
        'cbngo.org.br',
        'cbngo.com.br',
    ],

    /*
    |--------------------------------------------------------------------------
    | URL do site institucional CBN-GO
    |--------------------------------------------------------------------------
    */
    'site_url' => env('PORTAL_SITE_URL', 'https://cbngo.org.br'),

    /*
    |--------------------------------------------------------------------------
    | E-mail da secretaria para solicitação de acesso
    |--------------------------------------------------------------------------
    */
    'contact_email' => env('PORTAL_CONTACT_EMAIL', 'contato@cbngo.org.br'),

];
