<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Winthor\Input\SearchOrdersInput;
use App\Dto\Winthor\Output\AvailableOrderOutput;
use App\Dto\Winthor\Output\AvailableOrdersOutput;
use App\Dto\Winthor\Output\PriceContextOutput;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Repository\Order\OrderSnapshotRepository;
use App\Service\Commission\CommissionSquareService;
use App\Service\Commission\PriceContextResolverService;
use App\Service\CommissionRule\GetCurrentCommissionRuleService;
use App\Service\Finance\MoneyService;
use DateTimeImmutable;

final readonly class SearchAvailableOrdersService
{
    public function __construct(
        private OrderGatewayInterface $winthor,
        private OrderSnapshotRepository $snapshots,
        private GetCurrentCommissionRuleService $rules,
        private PriceContextResolverService $contexts,
        private CommissionSquareService $squares,
    ) {
    }

    public function search(SearchOrdersInput $input): AvailableOrdersOutput
    {
        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);

        if ($from > $to || 366 < $from->diff($to)->days) {
            throw new BusinessException('Informe um período válido de até 366 dias.');
        }

        $this->squares->validate($input->square, $input->mode);
        $rows = $this->winthor->search($input->customer, $input->from, $input->to, $input->square, $input->mode);

        $numbers = array_map(static fn (array $row): string => (string) $row['NUMPED'], $rows);

        $assigned = [];

        if ([] !== $numbers) {
            $snapshots = $this->snapshots->findBy(['orderNumber' => $numbers]);

            foreach ($snapshots as $snapshot) {
                if (null !== $snapshot->getCommission()) {
                    $assigned[$snapshot->getOrderNumber()] = true;
                }
            }
        }

        $rule = $this->rules->current();
        $items = [];

        foreach ($rows as $row) {
            $number = (string) $row['NUMPED'];

            if (true === isset($assigned[$number])) {
                continue;
            }

            $priceContext = null;
            $priceContextError = null;

            try {
                $context = $this->contexts->resolve($row, $rule, $input->square);
                $plan = (string) ($row['COMMISSION_NUMPR'] ?? '');

                if (false === in_array($plan, ['1', '2', '3', '4', '5', '6', '7'], true)) {
                    throw new BusinessException('Plano de pagamento sem coluna de preço válida. Confira PCPLPAG.NUMPR.');
                }

                $priceContext = new PriceContextOutput(
                    (int) $row['NUMREGIAO'],
                    (int) $context['psdRegion'],
                    null === $context['pscfRegion'] ? null : (int) $context['pscfRegion'],
                    (string) $row['CODPLPAG'],
                    'PVENDA'.$plan,
                    $rule['basis'],
                    $rule['version'],
                    $input->square,
                );
            } catch (BusinessException $exception) {
                $priceContextError = $exception->getMessage();
            }

            $items[] = new AvailableOrderOutput(
                $number,
                (string) $row['CODCLI'],
                (string) ($row['CLIENTE'] ?? ''),
                (string) ($row['DATA'] ?? ''),
                (string) $row['CODFILIAL'],
                MoneyService::normalize((string) $row['VLTOTAL']),
                $priceContext,
                $priceContextError,
                true === isset($row['NUMNOTA']) ? (string) $row['NUMNOTA'] : null,
                (int) $row['CODPRACA'],
            );
        }

        return new AvailableOrdersOutput($items);
    }
}
