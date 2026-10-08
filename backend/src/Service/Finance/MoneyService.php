<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Exception\Business\BusinessException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class MoneyService
{
    public static function decimal(string $value, int $scale): string
    {
        if (1 !== preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', $value)) {
            throw new BusinessException('Informe valores decimais positivos com ponto, sem separador de milhares.');
        }

        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
    }

    public static function normalize(string $value): string
    {
        return self::decimal($value, 2);
    }

    public static function positive(string $value): string
    {
        $amount = self::normalize($value);

        if (true === BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new BusinessException('O valor deve ser maior que zero.');
        }

        return $amount;
    }

    public static function net(string $gross, array $deductions): array
    {
        $sum = BigDecimal::of('0.00');

        foreach ($deductions as $value) {
            $sum = $sum->plus(self::positive($value));
        }

        $net = BigDecimal::of(self::positive($gross))->minus($sum);

        if (true === $net->isLessThanOrEqualTo(0)) {
            throw new BusinessException('As deduções devem ser menores que a comissão bruta.');
        }

        return ['gross' => self::normalize($gross), 'deductions' => (string) $sum->toScale(2), 'net' => (string) $net->toScale(2)];
    }
}
