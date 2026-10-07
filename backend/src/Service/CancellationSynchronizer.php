<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adjustment;
use App\Entity\OrderSnapshot;
use App\Entity\User;
use App\Exception\BusinessException;
use App\Integration\Winthor\MovementGatewayInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CancellationSynchronizer
{
    public function __construct(private EntityManagerInterface $em, private MovementGatewayInterface $movements, private AuditRecorder $audit)
    {
    }

    public function sync(string $customer, User $actor): void
    {
        $this->em->wrapInTransaction(function () use ($customer, $actor): void {
            $lockSql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
            $this->em->getConnection()->executeQuery($lockSql, ['source' => 'gcom:customer:'.$customer]);
            $this->capture($customer, $actor);
        });
    }

    private function capture(string $customer, User $actor): void
    {
        $orders = $this->em->getRepository(OrderSnapshot::class)->createQueryBuilder('o')
            ->where('o.customerCode = :customer')->andWhere('o.commission IS NOT NULL')
            ->setParameter('customer', $customer)->getQuery()->getResult();
        foreach ($orders as $order) {
            $source = 'winthor:cancellation:'.$order->getOrderNumber();

            if ($this->em->getRepository(Adjustment::class)->findOneBy(['sourceKey' => $source])) {
                continue;
            }
            $rows = $this->movements->cancelledOrder($order->getOrderNumber());

            if (!$rows) {
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
                if (!isset($quantities[$product]) || $quantities[$product]->isLessThan($quantity)) {
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

                if (!isset($calculation['rule']['percentage'], $calculation['items'])) {
                    throw new BusinessException('Pedido cancelado sem memória de cálculo suficiente: '.$order->getOrderNumber().'.', 409);
                }

                if ($calculation['rule']['subtractFreight']) {
                    $base = $base->minus((string) ($order->getHeader()['VLFRETE'] ?? '0'));
                }
                $gross = $base->multipliedBy($calculation['percentageApplied'] ?? $calculation['rule']['percentage'])->dividedBy(100, 2, RoundingMode::HalfUp);
            }
            // Returns already registered for the same order must not be charged again.
            $returns = BigDecimal::of(0);
            foreach ($this->em->getRepository(Adjustment::class)->findBy(['customerCode' => $customer, 'type' => 'return']) as $adjustment) {
                foreach ($adjustment->getSourceSnapshot()['items'] ?? [] as $line) {
                    if ($line['orderNumber'] === $order->getOrderNumber()) {
                        $returns = $returns->plus($line['deduction']);
                    }
                }
            }
            $amount = $gross->minus($returns);

            if (!$amount->isPositive()) {
                continue;
            }
            $snapshot = ['version' => 'winthor-cancellation-v1', 'orderNumber' => $order->getOrderNumber(), 'originalCommissionId' => $order->getCommission()->getId(), 'originalGross' => (string) $gross, 'returnDeductions' => (string) $returns, 'amount' => Money::positive((string) $amount), 'sourceRows' => $rows, 'capturedAt' => (new DateTimeImmutable())->format(\DATE_ATOM)];
            $this->em->wrapInTransaction(function () use ($source, $snapshot, $customer, $actor): void {
                $lockSql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
                $this->em->getConnection()->executeQuery($lockSql, ['source' => $source]);

                if ($this->em->getRepository(Adjustment::class)->findOneBy(['sourceKey' => $source])) {
                    return;
                }
                $adjustment = (new Adjustment())->setCustomerCode($customer)->setType('cancellation')->setAmount($snapshot['amount'])
                    ->setReason('Estorno da comissão original por cancelamento integral do pedido.')->setSourceReference('Pedido '.$snapshot['orderNumber'])->setCreatedBy($actor)->captureSource($source, $snapshot);
                $this->em->persist($adjustment);
                $this->audit->record($actor, 'cancellation.imported', $source, ['amount' => $snapshot['amount'], 'originalCommissionId' => $snapshot['originalCommissionId']]);
            });
        }
    }
}
