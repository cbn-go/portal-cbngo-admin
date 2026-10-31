<?php

namespace App\Http\Controllers\Strategy;

use App\Services\Strategy\MunicipalityMap;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a malha municipal baixada do IBGE para o mapa do Painel de Estratégia.
 * O navegador nunca consulta o IBGE diretamente.
 */
class MunicipalityMapGeoJsonController
{
    public function __invoke(Request $request, MunicipalityMap $map): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->is_active && $user->role->hasAdminPanelAccess(), 403);
        abort_unless($map->exists(), 404, 'Malha municipal ainda não sincronizada.');

        $response = new BinaryFileResponse($map->path(), 200, [
            'Content-Type' => 'application/geo+json',
        ], public: false, autoEtag: true, autoLastModified: true);

        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->isNotModified($request);

        return $response;
    }
}
