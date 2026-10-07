<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\BusinessException;
use DateTimeImmutable;

final class Input
{
    public static function text(array $data, string $field, int $max = 255, int $min = 1): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || mb_strlen(trim($value)) < $min || mb_strlen(trim($value)) > $max) {
            throw new BusinessException(sprintf('Campo "%s" inválido (%d a %d caracteres).', $field, $min, $max));
        }

        return trim($value);
    }

    public static function ids(array $data, string $field, bool $required = true): array
    {
        $values = $data[$field] ?? [];

        if (!is_array($values) || !array_is_list($values) || count($values) > 100 || ($required && !$values)) {
            throw new BusinessException('Lista de identificadores inválida: '.$field);
        }
        foreach ($values as $v) {
            if (!is_int($v) || $v < 1) {
                throw new BusinessException('Identificador inválido.');
            }
        }

        if (count($values) !== count(array_unique($values))) {
            throw new BusinessException('Identificadores repetidos.');
        }
        sort($values);

        return $values;
    }

    public static function date(array $data, string $field): DateTimeImmutable
    {
        $value = self::text($data, $field, 10, 10);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new BusinessException('Data inválida.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new BusinessException('Data inválida.');
        }

        return $date;
    }
}
