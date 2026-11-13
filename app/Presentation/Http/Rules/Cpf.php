<?php

declare(strict_types=1);

namespace App\Presentation\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CPF com 11 dígitos e dígitos verificadores corretos. */
final class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('O CPF informado não é válido.');
        }
    }

    public static function isValid(string $cpf): bool
    {
        // Sequências de um mesmo dígito passam no cálculo, mas não são CPFs válidos.
        if (preg_match('/^\d{11}$/', $cpf) !== 1 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * ($length + 1 - $i);
            }

            if ((int) $cpf[$length] !== ($sum * 10) % 11 % 10) {
                return false;
            }
        }

        return true;
    }
}
