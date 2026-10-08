<?php

declare(strict_types=1);

namespace App\Service\Adjustment;

use App\Entity\Adjustment\Adjustment;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Repository\Order\OrderSnapshotRepository;
use App\Service\Audit\AuditRecorderService;
use App\Service\Finance\MoneyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

final readonly class CancellationSynchronizerService
{
    public function __construct(private MovementGatewayInterface $movements, private AuditRecorderService $audit, private AdjustmentRepository $adjustments, private OrderSnapshotRepository $orders)
    {
    }

    public function sync(string $customer, User $actor): void
    {
        $this->adjustments->save(function () use ($customer, $actor): void {
            $this->adjustments->lockCustomer($customer);
            $this->capture($customer, $actor);
        });
    }

    private function capture(string $customer, User $actor): void
    {
        $orders = $this->orders->findCommissionedForCustomer($customer);

        foreach ($orders as $order) {
            $source = 'winthor:cancellation:'.$order->getOrderNumber();

            if (null !== $this->adjustments->findBySource($source)) {
                continue;
            }

            $rows = $this->movements->cancelledOrder($order->getOrderNumber());

            if ([] === $rows) {
                continue;
            }

            $quantities = [];
            foreach ($rows as $row) {
                $product = (string) $row['CODPROD'];
                $quantities[$product] = ($quantities[$product] ?? BigDecimal::of(0))->plus(BigDecimal::of((string) $row['QT'])->abs());
            }

            $originalQuantities = [];
            foreach ($order->getItems() as $item) {
                $product = $item->getProductCode();
                $originalQuantities[$product] = ($originalQuantities[$product] ?? BigDecimal::of(0))->plus($item->getQuantity());
            }

            foreach ($originalQuantities as $product => $quantity) {
                if (false === isset($quantities[$product]) || true === $quantities[$product]->isLessThan($quantity)) {
                    throw new BusinessException('Cancelamento parcial do pedido '.$order->getOrderNumber().': confira os itens antes de gerar nova comissão.', 409);
                }
            }

            $calculation = $order->getCommission()->getCalculation();
            $gross = null;
            foreach ($calculation['orders'] ?? [] as $amount) {
                if ($amount['orderNumber'] === $order->getOrderNumber()) {
                    $gross = BigDecimal::of($amount['grossAmount']);
                }
            }

            if (null === $gross) {
                // Older snapshots contain enough line data to recover this order's share.
                $base = BigDecimal::of(0);
                foreach ($calculation['items'] ?? [] as $line) {
                    if ($line['orderNumber'] === $order->getOrderNumber()) {
                        $base = $base->plus($line['sales'])->minus($line['reference']);
                    }
                }

                if (false === isset($calculation['rule']['percentage'], $calculation['items'])) {
                    throw new BusinessException('Pedido cancelado sem memória de cálculo suficiente: '.$order->getOrderNumber().'.', 409);
                }

                if (true === $calculation['rule']['subtractFreight']) {
                    $base = $base->minus((string) ($order->getHeader()['VLFRETE'] ?? '0'));
                }

                $gross = $base->multipliedBy($calculation['percentageApplied'] ?? $calculation['rule']['percentage'])->dividedBy(100, 2, RoundingMode::HalfUp);
            }
            // Returns already registered for the same order must not be charged again.
            $returns = BigDecimal::of(0);
            foreach ($this->adjustments->findReturnsForCustomer($customer) as $adjustment) {
                foreach ($adjustment->getSourceSnapshot()['items'] ?? [] as $line) {
                    if ($line['orderNumber'] === $order->getOrderNumber()) {
                        $returns = $returns->plus($line['deduction']);
                    }
                }
            }

            $amount = $gross->minus($returns);

            if (false === $amount->isPositive()) {
                continue;
            }

            $snapshot = ['version' => 'winthor-cancellation-v1', 'orderNumber' => $order->getOrderNumber(), 'originalCommissionId' => $order->getCommission()->getId(), 'originalGross' => (string) $gross, 'returnDeductions' => (string) $returns, 'amount' => MoneyService::positive((string) $amount), 'sourceRows' => $rows, 'capturedAt' => (new DateTimeImmutable())->format(\DATE_ATOM)];
            $this->adjustments->save(function () use ($source, $snapshot, $customer, $actor): void {
                $this->adjustments->lockSource($source);

                if (null !== $this->adjustments->findBySource($source)) {
                    return;
                }

                $adjustment = (new Adjustment())->setCustomerCode($customer)->setType('cancellation')->setAmount($snapshot['amount'])
                    ->setReason('Estorno da comissão original por cancelamento integral do pedido.')->setSourceReference('Pedido '.$snapshot['orderNumber'])->setCreatedBy($actor)->captureSource($source, $snapshot);
                $this->adjustments->store($adjustment);
                $this->audit->record($actor, 'cancellation.imported', $source, ['amount' => $snapshot['amount'], 'originalCommissionId' => $snapshot['originalCommissionId']]);
            });
        }
    }
}
