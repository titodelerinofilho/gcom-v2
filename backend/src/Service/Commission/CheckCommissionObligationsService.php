<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Output\CommissionChecksOutput;
use App\Dto\Commission\Output\OverdueTitleOutput;
use App\Dto\Commission\Output\ReturnCandidateOutput;
use App\Entity\User\User;
use App\Integration\Winthor\MovementGatewayInterface;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Service\Adjustment\CancellationSynchronizerService;
use App\Service\Adjustment\ReturnSynchronizerService;
use App\Service\Finance\MoneyService;
use Brick\Math\BigDecimal;
use DateTimeImmutable;

final readonly class CheckCommissionObligationsService
{
    public function __construct(
        private MovementGatewayInterface $movements,
        private ReturnSynchronizerService $returns,
        private AdjustmentRepository $adjustments,
        private CancellationSynchronizerService $cancellations,
        private CorrelateReturnPaymentsService $returnPayments,
    ) {
    }

    public function check(string $customer, string $mode, User $actor, ?array $selectedReturns = null): CommissionChecksOutput
    {
        $titles = array_map(static fn (array $row): OverdueTitleOutput => new OverdueTitleOutput($row), $this->movements->overdue($customer));
        $total = BigDecimal::of(0);
        foreach ($titles as $title) {
            $total = $total->plus($title->amount);
        }

        $rows = $this->movements->returns($customer, 'atg' === $mode);
        $this->returns->sync($customer, 'atg' === $mode, $actor, $selectedReturns ?? [], $rows);
        $grouped = [];
        foreach ($rows as $row) {
            if (true === isset($row['NUMTRANSENT'])) {
                $grouped[(string) $row['NUMTRANSENT']][] = $row;
            }
        }
        foreach ($this->adjustments->findReturnsForCustomer($customer) as $adjustment) {
            if (null === $adjustment->getCommission() && null !== $adjustment->getSourceKey()) {
                $transaction = str_replace('winthor:return:', '', $adjustment->getSourceKey());
                $grouped[$transaction] ??= $adjustment->getSourceSnapshot()['sourceRows'] ?? [];
            }
        }
        $returnItems = $this->returnPayments->correlate($customer, $grouped);
        $candidates = [];
        foreach ($grouped as $transaction => $lines) {
            $transaction = (string) $transaction;
            $existing = $this->adjustments->findBySource('winthor:return:'.$transaction);

            if (null !== $existing?->getCommission() || [] === $lines) {
                continue;
            }
            $first = $lines[0];
            $candidates[] = new ReturnCandidateOutput($transaction, (string) $first['NUMNOTA'], (string) ($first['FINAL_CUSTOMER'] ?? $first['CODCLI'] ?? $customer), (string) ($first['CLIENTE'] ?? ''), $first['MOVEMENT_DATE'] ?? null, array_values(array_unique(array_map(static fn (array $line): string => (string) $line['NUMPED'], $lines))), $existing?->getAmount(), null === $selectedReturns ? null !== $existing : true === in_array($transaction, $selectedReturns, true), $returnItems[$transaction]);
        }
        $this->cancellations->sync($customer, $actor);

        if (null !== $selectedReturns) {
            $pending = $this->adjustments->findBy(['customerCode' => $customer, 'commission' => null]);
            foreach ($pending as $adjustment) {
                if ('return' !== $adjustment->getType() || null === $adjustment->getSourceKey() || true === in_array(str_replace('winthor:return:', '', $adjustment->getSourceKey()), $selectedReturns, true)) {
                    continue;
                }

                foreach ($pending as $cancellation) {
                    if ('cancellation' !== $cancellation->getType() || false === BigDecimal::of($cancellation->getSourceSnapshot()['returnDeductions'] ?? '0')->isPositive()) {
                        continue;
                    }

                    foreach ($adjustment->getSourceSnapshot()['items'] ?? [] as $line) {
                        if ($line['orderNumber'] === ($cancellation->getSourceSnapshot()['orderNumber'] ?? null)) {
                            throw new \App\Exception\Business\BusinessException('Esta devolução compõe um estorno de cancelamento já registrado. Selecione-a para manter o valor integral do estorno.', 409);
                        }
                    }
                }
            }
        }

        $canonicalTitles = array_map(static fn (OverdueTitleOutput $title): array => get_object_vars($title), $titles);
        usort($canonicalTitles, static fn (array $first, array $second): int => [$first['customerCode'], $first['transaction'], $first['installment']] <=> [$second['customerCode'], $second['transaction'], $second['installment']]);

        return new CommissionChecksOutput($customer, (new DateTimeImmutable())->format(\DATE_ATOM), $titles, MoneyService::normalize((string) $total), count($candidates), $candidates, hash('sha256', json_encode(['titles' => $canonicalTitles, 'returns' => $grouped, 'returnPayments' => $returnItems], \JSON_THROW_ON_ERROR)));
    }
}
