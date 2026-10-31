<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('strategy:sync-indicators')
    ->monthly()
    ->description('Sincronização mensal de indicadores territoriais do SIDRA/IBGE');

Schedule::command('strategy:sync-map')
    ->monthly()
    ->description('Atualização mensal da malha municipal de Goiás (IBGE Malhas) para o mapa');
