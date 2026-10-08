<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Entity\Order\OrderSnapshot;
use App\Exception\Business\BusinessException;
use App\Service\Finance\MoneyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class CommissionCalculatorService
{
    public function __construct(
        private readonly PriceContextResolverService $contexts,
        private readonly ComboPriceCalculatorService $combos,
        private readonly CommissionItemAllocationService $itemAllocation,
    ) {
    }

    /** @param OrderSnapshot[] $orders */
    public function calculate(array $orders, array $rule, string $mode = 'normal', ?int $square = null): array
    {
        if (false === in_array($mode, ['normal', 'atg'], true)) {
            throw new BusinessException('Modalidade de comissão inválida.');
        }

        $basis = 'atg' === $mode ? 'margin_table' : $rule['basis'];
        $percentage = 'atg' === $mode ? ($rule['atgPercentage'] ?? $rule['percentage']) : $rule['percentage'];
        $sales = BigDecimal::of(0);
        $reference = BigDecimal::of(0);
        $freight = BigDecimal::of(0);
        $lines = [];
        $orderAmounts = [];
        foreach ($orders as $order) {
            $header = $order->getHeader();
            $orderSales = BigDecimal::of(0);
            $orderReference = BigDecimal::of(0);
            $context = $this->contexts->resolve($header, $rule, $square);
            $freight = $freight->plus(MoneyService::decimal((string) ($header['VLFRETE'] ?? '0'), 6));
            foreach ($order->getItems() as $item) {
                $raw = $item->getRaw();
                $unitReference = '0';
                $combo = true === str_contains(mb_strtoupper($item->getDescription()), 'COMBO')
                    ? $this->combos->prices($raw, $context) : null;

                if ('margin_psd' === $basis) {
                    $plan = (string) ($header['COMMISSION_NUMPR'] ?? '');
                    $unitReference = $combo['psd'] ?? $raw['COMMISSION_PRICES'][(string) $context['psdRegion']]['PVENDA'.$plan] ?? null;

                    if (null === $unitReference || (null === $combo && false === in_array($plan, ['1', '2', '3', '4', '5', '6', '7'], true))) {
                        throw new BusinessException('Pedido '.$order->getOrderNumber().' sem preço PSD preservado para a região/plano. Importe pedidos com os dados de preços completos.');
                    }
                } elseif ('margin_table' === $basis) {
                    $unitReference = $raw['PTABELA'] ?? null;

                    if (null === $unitReference) {
                        throw new BusinessException('Item sem PTABELA preservado.');
                    }
                }

                $price = BigDecimal::of(null !== $combo && 'margin_psd' === $basis ? $unitReference : MoneyService::decimal((string) $unitReference, 6));

                if ('sales' !== $basis && false === $price->isPositive()) {
                    throw new BusinessException('Preço de referência deve ser maior que zero.');
                }

                if (true === $rule['applyReferenceDiscount'] && 'sales' !== $basis) {
                    if (false === isset($raw['PERCENTUALDESC'])) {
                        throw new BusinessException('Item sem PERCENTUALDESC preservado para aplicar desconto na referência.');
                    }

                    $discount = BigDecimal::of(MoneyService::decimal((string) $raw['PERCENTUALDESC'], 6));

                    if (true === $discount->isGreaterThan(100)) {
                        throw new BusinessException('Desconto de referência inválido.');
                    }

                    $price = $price->multipliedBy(BigDecimal::of(1)->minus($discount->dividedBy(100, 8)));
                }

                $quantity = BigDecimal::of($item->getQuantity());
                $sale = $quantity->multipliedBy($item->getUnitPrice());
                $cost = $quantity->multipliedBy($price);
                $orderSales = $orderSales->plus($sale);
                $orderReference = $orderReference->plus($cost);
                $sales = $sales->plus($sale);
                $reference = $reference->plus($cost);
                $plan = (string) ($header['COMMISSION_NUMPR'] ?? '');
                $psd = $combo['psd'] ?? $raw['COMMISSION_PRICES'][(string) $context['psdRegion']]['PVENDA'.$plan] ?? null;
                $pscf = $combo['pscf'] ?? (null === $context['pscfRegion'] ? null : ($raw['COMMISSION_PRICES'][(string) $context['pscfRegion']]['PVENDA'.$plan] ?? null));

                if (true === isset($rule['priceContexts']) && (null === $psd || null === $pscf)) {
                    throw new BusinessException('Pedido '.$order->getOrderNumber().' sem preços PSD/PSCF para o pareamento configurado. Reimporte um pedido novo com preços completos.');
                }

                $lines[] = ['description' => $item->getDescription(), 'unit' => (string) ($raw['UNIDADE'] ?? ''), 'discountPercentage' => (string) ($raw['PERCENTUALDESC'] ?? '0'), 'percentageApplied' => $percentage, 'commissionBeforeFreight' => (string) $sale->minus($cost)->multipliedBy($percentage)->multipliedBy('0.01'), 'combo' => $combo, 'context' => $context, 'paymentPlan' => $header['CODPLPAG'] ?? null, 'priceColumn' => $combo['priceColumn'] ?? 'PVENDA'.$plan, 'unitPsd' => $psd, 'unitPscf' => $pscf, 'unitTable' => $raw['PTABELA'] ?? null, 'margin' => (string) $sale->minus($cost), 'orderNumber' => $order->getOrderNumber(), 'itemId' => $item->getId(), 'productCode' => $item->getProductCode(), 'quantity' => $item->getQuantity(), 'unitSale' => $item->getUnitPrice(), 'unitReference' => (string) $price, 'sales' => (string) $sale, 'reference' => (string) $cost];
            }

            $orderFreight = true === $rule['subtractFreight'] ? MoneyService::decimal((string) ($header['VLFRETE'] ?? '0'), 6) : '0';
            $orderBase = $orderSales->minus($orderReference)->minus($orderFreight);
            $orderAmounts[] = ['orderNumber' => $order->getOrderNumber(), 'context' => $context, 'sales' => (string) $orderSales, 'reference' => (string) $orderReference, 'deductedFreight' => $orderFreight, 'baseAmount' => (string) $orderBase, 'grossAmount' => (string) $orderBase->multipliedBy($percentage)->dividedBy(100, 2, RoundingMode::HalfUp)];
        }

        $deductedFreight = true === $rule['subtractFreight'] ? $freight : BigDecimal::of(0);
        $base = $sales->minus($deductedFreight)->minus($reference);

        if (false === $base->isPositive()) {
            throw new BusinessException('A base de cálculo deve ser positiva após referência e frete.');
        }

        $gross = $base->multipliedBy($percentage)->dividedBy(100, 2, RoundingMode::HalfUp);

        $allocated = BigDecimal::of(0);
        foreach ($orderAmounts as $amount) {
            $allocated = $allocated->plus($amount['grossAmount']);
        }

        $last = array_key_last($orderAmounts);
        $orderAmounts[$last]['grossAmount'] = (string) BigDecimal::of($orderAmounts[$last]['grossAmount'])->plus($gross->minus($allocated));

        return $this->itemAllocation->allocate(['square' => $square, 'mode' => $mode, 'effectiveBasis' => $basis, 'percentageApplied' => $percentage, 'orders' => $orderAmounts, 'version' => 'configured-v2', 'rule' => $rule, 'sales' => (string) $sales, 'reference' => (string) $reference, 'freight' => (string) $freight, 'deductedFreight' => (string) $deductedFreight, 'baseAmount' => (string) $base, 'grossAmount' => MoneyService::positive((string) $gross), 'items' => $lines, 'rounding' => 'half_up_final_gross_2_decimals']);
    }
}
