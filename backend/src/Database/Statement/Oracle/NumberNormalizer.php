<?php

declare(strict_types=1);

namespace App\Database\Statement\Oracle;

use App\Exception\Database\DatabaseException;
use Brick\Math\BigDecimal;

final class NumberNormalizer
{
    public static function normalize(string $value): string
    {
        $decimal = str_replace(',', '.', $value);

        if (1 !== preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[Ee][+-]?[0-9]+)?$/D', $decimal)) {
            throw new DatabaseException('Formato numérico Oracle inválido.');
        }

        if (true === str_starts_with($decimal, '.')) {
            $decimal = '0'.$decimal;
        } elseif (true === str_starts_with($decimal, '-.') || true === str_starts_with($decimal, '+.')) {
            $decimal = substr($decimal, 0, 1).'0'.substr($decimal, 1);
        }

        return (string) BigDecimal::of($decimal);
    }
}
