<?php

namespace App\Support;

/**
 * Regras de links cadastrados no painel para o portal público: um caminho
 * do site ("/eventos") ou uma URL http(s) completa.
 */
class LinkTarget
{
    public static function isValid(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        if (self::isSitePath($url)) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Externo = URL completa para um domínio fora de config('portal.internal_hosts').
     * Caminhos do site e domínios da convenção (e subdomínios) são internos.
     */
    public static function isExternal(?string $url): bool
    {
        if (blank($url) || self::isSitePath($url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        /** @var list<string> $internalHosts */
        $internalHosts = config('portal.internal_hosts', []);

        foreach ($internalHosts as $internal) {
            if ($host === $internal || str_ends_with($host, '.'.$internal)) {
                return false;
            }
        }

        return true;
    }

    private static function isSitePath(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }
}
