<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Exception\Business\BusinessException;

final class PriceContextResolverService
{
    public function __construct(private readonly CommissionSquareService $squares = new CommissionSquareService())
    {
    }

    public function resolve(array $header, array $rule, ?int $square = null): array
    {
        $branch = (string) ($header['CODFILIAL'] ?? '');
        $region = (int) ($header['NUMREGIAO'] ?? 0);

        if (null !== $square) {
            $selected = $this->squares->get($square);
            $fallback = null;

            foreach ($rule['priceContexts'] ?? [] as $context) {
                if ($selected->psdRegion !== $context['psdRegion'] || $selected->pscfRegion !== $context['pscfRegion']) {
                    continue;
                }

                $resolved = [...$context, 'branch' => $branch, 'orderRegion' => $region, 'comparisonSquare' => $square];

                if ((string) $context['branch'] === $branch) {
                    return $resolved;
                }

                if ('*' === $context['branch']) {
                    $fallback = $resolved;
                }
            }

            if (null !== $fallback) {
                return $fallback;
            }

            throw new BusinessException('Configure o pareamento PSD/PSCF da praça selecionada para a filial '.$branch.'.', 409);
        }

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
