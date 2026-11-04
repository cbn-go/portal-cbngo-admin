<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsSet
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && (bool) $user->must_set_password) {
            $currentPath = trim($request->path(), '/');

            // Permite rota de definir senha e logout
            if (! str_starts_with($currentPath, 'ormiban/definir-senha') &&
                ! str_ends_with($currentPath, 'logout')) {
                return redirect()->to('/ormiban/definir-senha');
            }
        }

        return $next($request);
    }
}
