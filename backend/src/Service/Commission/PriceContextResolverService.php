<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Exception\Business\BusinessException;

final class PriceContextResolverService
{
    public function resolve(array $header, array $rule): array
    {
        $branch = (string) ($header['CODFILIAL'] ?? '');
        $region = (int) ($header['NUMREGIAO'] ?? 0);

        // Historical rules remain reproducible; new rules always require a context map.
        if (false === array_key_exists('priceContexts', $rule)) {
            return ['branch' => $branch, 'orderRegion' => $region, 'psdRegion' => $rule['psdRegion'], 'pscfRegion' => null, 'legacyRule' => true];
        }

        $fallback = null;

        foreach ($rule['priceContexts'] as $context) {
            if ($context['orderRegion'] !== $region) {
                continue;
            }

            if ((string) $context['branch'] === $branch) {
                return [...$context, 'branch' => $branch];
            }

            if ('*' === $context['branch']) {
                $fallback = [...$context, 'branch' => $branch];
            }
        }

        if (null !== $fallback) {
            return $fallback;
        }

        throw new BusinessException('Sem pareamento PSD/PSCF para filial '.$branch.' e tabela '.$region.'. Solicite a configuração ao administrador.');
    }
}
