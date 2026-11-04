<?php

namespace App\Support;

class Cpf
{
    /**
     * Normaliza CPF removendo qualquer caractere não numérico.
     * Retorna 11 dígitos numéricos ou null caso não atinja exatamente 11 dígitos.
     */
    public static function normalize(?string $cpf): ?string
    {
        if (blank($cpf)) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $cpf);

        return strlen($digits) === 11 ? $digits : null;
    }

    /**
     * Valida o CPF pelo algoritmo oficial Módulo 11 da Receita Federal.
     */
    public static function isValid(?string $cpf): bool
    {
        $digits = self::normalize($cpf);

        if ($digits === null) {
            return false;
        }

        // Rejeita sequências com todos os dígitos iguais (ex: 00000000000, 11111111111)
        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        // Validação do 1º dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $d1 = ($remainder < 2) ? 0 : 11 - $remainder;
        if ((int) $digits[9] !== $d1) {
            return false;
        }

        // Validação do 2º dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $digits[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $d2 = ($remainder < 2) ? 0 : 11 - $remainder;

        return (int) $digits[10] === $d2;
    }

    /**
     * Formata CPF com máscara padrão 999.999.999-99.
     */
    public static function format(?string $cpf): ?string
    {
        $digits = self::normalize($cpf);

        if ($digits === null) {
            return null;
        }

        return (string) preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    }
}
