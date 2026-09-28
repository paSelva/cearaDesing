<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ válido pelo dígito verificador (P4), no formato numérico ou alfanumérico
 * (IN RFB 2.229/2024, P81). Os 12 primeiros caracteres podem ser letras ou dígitos;
 * os 2 verificadores são sempre dígitos. Aceita com ou sem máscara.
 */
final class Cnpj implements ValidationRule
{
    private const WEIGHTS_FIRST = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const WEIGHTS_SECOND = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function normalize(string $value): string
    {
        return preg_replace('/[^0-9A-Z]/', '', strtoupper($value)) ?? '';
    }

    public static function isValid(string $value): bool
    {
        $cnpj = self::normalize($value);

        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) !== 1 || preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        return (int) $cnpj[12] === self::checkDigit(substr($cnpj, 0, 12), self::WEIGHTS_FIRST)
            && (int) $cnpj[13] === self::checkDigit(substr($cnpj, 0, 13), self::WEIGHTS_SECOND);
    }

    /** @param list<int> $weights */
    private static function checkDigit(string $base, array $weights): int
    {
        $sum = 0;
        foreach (str_split($base) as $i => $char) {
            // Valor oficial: código ASCII - 48 (dígitos 0–9 valem 0–9; A vale 17, ..., Z vale 42).
            $sum += (ord($char) - 48) * $weights[$i];
        }
        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('O CNPJ informado não é válido.');
        }
    }
}
