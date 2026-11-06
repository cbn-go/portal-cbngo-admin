<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Saudação e data das telas iniciais dos painéis, sempre no horário de Brasília.
 */
final class PanelGreeting
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public static function greeting(?string $fullName, string $fallback): string
    {
        $hour = (int) Carbon::now(self::TIMEZONE)->format('H');

        $greeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Bom dia',
            $hour >= 12 && $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };

        $firstName = Str::of(filled($fullName) ? $fullName : $fallback)->trim()->explode(' ')->first();

        return "{$greeting}, {$firstName}";
    }

    public static function today(): string
    {
        $date = Carbon::now(self::TIMEZONE)
            ->locale('pt_BR')
            ->translatedFormat('l, d \d\e F \d\e Y');

        return ucfirst($date);
    }
}
