<?php

declare(strict_types=1);

namespace App\Service\Commission;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class CommissionItemAllocationService
{
    public function allocate(array $calculation): array
    {
        if (true === isset($calculation['itemAllocationVersion'])) {
            return $calculation;
        }

        $percentage = $calculation['percentageApplied'] ?? ('atg' === ($calculation['mode'] ?? 'normal') ? ($calculation['rule']['atgPercentage'] ?? $calculation['rule']['percentage'] ?? null) : ($calculation['rule']['percentage'] ?? null));

        if (null === $percentage || [] === ($calculation['items'] ?? []) || [] === ($calculation['orders'] ?? []) || false === isset($calculation['grossAmount'])) {
            return $calculation;
        }

        $groups = [];
        $targetTotal = BigDecimal::of(0);
        foreach ($calculation['orders'] as $order) {
            if (false === isset($order['orderNumber'], $order['grossAmount'], $order['deductedFreight'])) {
                return $calculation;
            }

            $number = $order['orderNumber'];
            $groups[$number] ??= ['indices' => [], 'sales' => BigDecimal::of(0), 'reference' => BigDecimal::of(0), 'freight' => BigDecimal::of(0), 'target' => BigDecimal::of(0)];
            $groups[$number]['freight'] = $groups[$number]['freight']->plus($order['deductedFreight']);
            $groups[$number]['target'] = $groups[$number]['target']->plus($order['grossAmount']);
            $targetTotal = $targetTotal->plus($order['grossAmount']);
        }

        if (false === $targetTotal->isEqualTo($calculation['grossAmount'])) {
            return $calculation;
        }

        foreach ($calculation['items'] as $index => $line) {
            if (false === isset($line['orderNumber'], $line['sales'], $line['reference'], $groups[$line['orderNumber']])) {
                return $calculation;
            }

            $number = $line['orderNumber'];
            $groups[$number]['indices'][] = $index;
            $groups[$number]['sales'] = $groups[$number]['sales']->plus($line['sales']);
            $groups[$number]['reference'] = $groups[$number]['reference']->plus($line['reference']);
        }

        $base = BigDecimal::of(0);
        foreach ($groups as $group) {
            if ([] === $group['indices'] || false === $group['sales']->isPositive()) {
                return $calculation;
            }

            $base = $base->plus($group['sales']->minus($group['reference'])->minus($group['freight']));
        }

        // Historical allocations use only the saved rule and must reconcile with the saved total.
        if (false === $base->multipliedBy($percentage)->dividedBy(100, 2, RoundingMode::HalfUp)->isEqualTo($targetTotal)) {
            return $calculation;
        }

        $items = $calculation['items'];
        foreach ($groups as $group) {
            $freightAllocated = BigDecimal::of(0);
            $commissionAllocated = BigDecimal::of(0);
            $remainders = [];
            $lastIndex = $group['indices'][array_key_last($group['indices'])];
            foreach ($group['indices'] as $index) {
                $line = $items[$index];
                $freight = $index === $lastIndex ? $group['freight']->minus($freightAllocated) : $group['freight']->multipliedBy($line['sales'])->dividedBy($group['sales'], 12, RoundingMode::HalfUp);
                $freightAllocated = $freightAllocated->plus($freight);
                $margin = BigDecimal::of($line['sales'])->minus($line['reference']);
                $exact = $margin->minus($freight)->multipliedBy($percentage)->multipliedBy('0.01');
                $rounded = $exact->toScale(2, RoundingMode::HalfUp);
                $commissionAllocated = $commissionAllocated->plus($rounded);
                $remainders[$index] = $exact->minus($rounded);
                $items[$index]['margin'] = (string) $margin;
                $items[$index]['percentageApplied'] = $percentage;
                $items[$index]['commissionBeforeFreight'] = (string) $margin->multipliedBy($percentage)->multipliedBy('0.01');
                $items[$index]['allocatedFreight'] = (string) $freight;
                $items[$index]['commissionAmount'] = (string) $rounded;
                $items[$index]['roundingAdjustment'] = '0.00';
            }

            $remainingCents = $group['target']->minus($commissionAllocated)->multipliedBy(100)->toInt();
            $direction = $remainingCents < 0 ? '-0.01' : '0.01';
            $indices = $group['indices'];
            usort($indices, static function (int $first, int $second) use ($remainders, $remainingCents): int {
                $comparison = $remainders[$first]->compareTo($remainders[$second]);

                return 0 === $comparison ? $first <=> $second : ($remainingCents < 0 ? $comparison : -$comparison);
            });

            // Allocate residual cents to the largest rounding remainders, keeping stable item order for ties.
            for ($cent = 0; $cent < abs($remainingCents); ++$cent) {
                $index = $indices[$cent % count($indices)];
                $items[$index]['commissionAmount'] = (string) BigDecimal::of($items[$index]['commissionAmount'])->plus($direction);
                $items[$index]['roundingAdjustment'] = (string) BigDecimal::of($items[$index]['roundingAdjustment'])->plus($direction);
            }
        }

        return [...$calculation, 'items' => $items, 'itemAllocationVersion' => 'sales_weighted_freight_v1'];
    }
}
