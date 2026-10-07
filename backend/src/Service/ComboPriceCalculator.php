<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\BusinessException;
use Brick\Math\BigDecimal;

final class ComboPriceCalculator
{
    public function prices(array $raw, array $context): array
    {
        $components = array_values(array_filter($raw['COMMISSION_COMPOSITION'] ?? [], static fn (array $c): bool => (string) $c['COMPOSITION_BRANCH'] === $context['branch'] && (int) $c['COMPOSITION_REGION'] === $context['psdRegion']));
        $configurations = array_unique(array_column($components, 'CODPRECOCESTA'));

        if (1 !== count($configurations)) {
            throw new BusinessException('Combo sem composição única para a filial/tabela PSD. Valide PCPRECOCESTAC.');
        }
        $psd = BigDecimal::of(0);
        $pscf = BigDecimal::of(0);
        $lines = [];
        foreach ($components as $component) {
            $quantity = BigDecimal::of(Money::decimal((string) $component['QTMP'], 6));
            $unitPsd = $component['PSD_UNIT'] ?? null;
            $unitPscf = $component['COMMISSION_COMPONENT_PRICES'][(string) $context['pscfRegion']] ?? null;

            if (null === $unitPsd || null === $unitPscf || !$quantity->isPositive()) {
                throw new BusinessException('Componente de combo sem quantidade/preços PSD/PSCF completos.');
            }
            $pricePsd = BigDecimal::of(Money::decimal((string) $unitPsd, 6));
            $pricePscf = BigDecimal::of(Money::decimal((string) $unitPscf, 6));

            if (!$pricePsd->isPositive() || !$pricePscf->isPositive()) {
                throw new BusinessException('Preço de componente deve ser positivo.');
            }
            $psd = $psd->plus($quantity->multipliedBy($pricePsd));
            $pscf = $pscf->plus($quantity->multipliedBy($pricePscf));
            $lines[] = ['productCode' => (string) $component['CODPRODMP'], 'quantityPerCombo' => (string) $quantity, 'unitPsd' => (string) $pricePsd, 'unitPscf' => (string) $pricePscf, 'configuration' => (string) $component['CODPRECOCESTA']];
        }

        return ['psd' => (string) $psd, 'pscf' => (string) $pscf, 'components' => $lines, 'quantityBasis' => 'per_combo_unit', 'priceColumn' => 'PVENDA1'];
    }
}
