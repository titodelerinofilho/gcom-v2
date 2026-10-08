<?php

declare(strict_types=1);

namespace App\Service\Adjustment;

use App\Exception\Business\BusinessException;
use App\Service\Commission\PriceContextResolverService;
use App\Service\Finance\MoneyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

final class ReturnCalculatorService
{
    public function __construct(private readonly PriceContextResolverService $contexts)
    {
    }

    public function calculate(array $rows, array $rule, bool $atg): array
    {
        $percentage = $rule[true === $atg ? 'atgReturnPercentage' : 'returnPercentage'] ?? (true === $atg ? '100' : '80');
        $total = BigDecimal::of(0);
        $lines = [];
        foreach ($rows as $row) {
            $context = $this->contexts->resolve($row, $rule);
            $unit = $row['COMMISSION_RETURN_PRICES'][(string) $context['psdRegion']] ?? null;

            if (null === $unit || false === BigDecimal::of(MoneyService::decimal((string) $unit, 6))->isPositive()) {
                throw new BusinessException('Devolução sem PTABELA1 PSD para filial/tabela configurada.');
            }

            $quantity = BigDecimal::of(MoneyService::decimal((string) $row['QT'], 6));
            $sale = $quantity->multipliedBy(MoneyService::decimal((string) $row['PUNIT'], 6));
            $reference = $quantity->multipliedBy(MoneyService::decimal((string) $unit, 6));
            $difference = $sale->minus($reference);

            if (true === $difference->isNegative()) {
                throw new BusinessException('Devolução com margem negativa: confira a tabela antes de registrar a dedução.');
            }

            $deduction = $difference->multipliedBy($percentage)->dividedBy(100, 2, RoundingMode::HalfUp);
            $total = $total->plus($deduction);
            $lines[] = ['context' => $context, 'orderNumber' => (string) $row['NUMPED'], 'productCode' => (string) $row['CODPROD'], 'invoice' => (string) $row['NUMNOTA'], 'quantity' => (string) $quantity, 'sale' => (string) $sale, 'reference' => (string) $reference, 'difference' => (string) $difference, 'deduction' => (string) $deduction];
        }

        return ['version' => 'winthor-return-v1', 'atg' => $atg, 'rule' => $rule, 'percentage' => $percentage, 'referenceColumn' => 'PTABELA1', 'amount' => MoneyService::positive((string) $total), 'items' => $lines, 'sourceRows' => $rows, 'capturedAt' => (new DateTimeImmutable())->format(\DATE_ATOM)];
    }
}
