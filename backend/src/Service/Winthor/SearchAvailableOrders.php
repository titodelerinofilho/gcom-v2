<?php

declare(strict_types=1);

namespace App\Service\Winthor;

use App\Dto\Winthor\Input\SearchOrdersInput;
use App\Dto\Winthor\Output\AvailableOrderOutput;
use App\Dto\Winthor\Output\AvailableOrdersOutput;
use App\Dto\Winthor\Output\PriceContextOutput;
use App\Exception\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Repository\OrderSnapshotRepository;
use App\Service\CommissionRules;
use App\Service\Money;
use App\Service\PriceContextResolver;
use DateTimeImmutable;

final readonly class SearchAvailableOrders
{
    public function __construct(
        private OrderGatewayInterface $winthor,
        private OrderSnapshotRepository $snapshots,
        private CommissionRules $rules,
        private PriceContextResolver $contexts,
    ) {
    }

    public function search(SearchOrdersInput $input): AvailableOrdersOutput
    {
        $from = new DateTimeImmutable($input->from);
        $to = new DateTimeImmutable($input->to);

        if ($from > $to || 366 < $from->diff($to)->days) {
            throw new BusinessException('Informe um período válido de até 366 dias.');
        }

        $rows = $this->winthor->search($input->customer, $input->from, $input->to, null);
        $numbers = array_map(static fn (array $row): string => (string) $row['NUMPED'], $rows);
        $assigned = [];

        if ([] !== $numbers) {
            foreach ($this->snapshots->findBy(['orderNumber' => $numbers]) as $snapshot) {
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
                $context = $this->contexts->resolve($row, $rule);
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
                Money::normalize((string) $row['VLTOTAL']),
                $priceContext,
                $priceContextError,
            );
        }

        return new AvailableOrdersOutput($items);
    }
}
