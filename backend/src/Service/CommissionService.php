<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adjustment;
use App\Entity\Commission;
use App\Entity\OrderSnapshot;
use App\Entity\PaymentLink;
use App\Entity\User;
use App\Exception\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Integration\Winthor\PaymentGateway;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class CommissionService
{
    public function __construct(private EntityManagerInterface $em, private AuditRecorder $audit, private PaymentGateway $payments, private CommissionRules $rules, private CommissionCalculator $calculator, private OrderGatewayInterface $winthor, private CancellationSynchronizer $cancellations)
    {
    }

    public function create(array $data, User $actor): Commission
    {
        $orderIds = Input::ids($data, 'orderIds');
        $adjustmentIds = Input::ids($data, 'adjustmentIds', false);
        $first = $this->em->find(OrderSnapshot::class, $orderIds[0]);

        if ($first) {
            $this->cancellations->sync($first->getCustomerCode(), $actor);
        }
        foreach (['grossAmount', 'percentage', 'basis', 'psdRegion', 'subtractFreight', 'applyReferenceDiscount', 'priceContexts', 'atgPercentage', 'returnPercentage', 'atgReturnPercentage', 'calculation'] as $key) {
            if (array_key_exists($key, $data)) {
                throw new BusinessException('A fórmula e o valor são definidos no servidor. Não envie '.$key.'.');
            }
        }
        $reason = Input::text($data, 'reason', 2000, 10);

        return $this->em->wrapInTransaction(function () use ($orderIds, $adjustmentIds, $data, $reason, $actor) {
            $rule = $this->rules->current();

            if (($data['ruleVersion'] ?? null) !== $rule['version']) {
                throw new BusinessException('A regra de cálculo mudou. Refaça a simulação antes de gerar a comissão.', 409);
            }
            $commission = new Commission();
            $orders = [];
            foreach ($orderIds as $id) {
                $order = $this->em->find(OrderSnapshot::class, $id, LockMode::PESSIMISTIC_WRITE);

                if (!$order) {
                    throw new BusinessException('Pedido não encontrado.', 404);
                }

                if ($order->getCommission()) {
                    throw new BusinessException('Pedido já tem comissão.', 409);
                }

                if ($orders && $order->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                    throw new BusinessException('Os pedidos devem pertencer ao mesmo cliente.');
                }
                $orders[] = $order;
            }
            $customerLockSql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
            $this->em->getConnection()->executeQuery($customerLockSql, ['source' => 'gcom:customer:'.$orders[0]->getCustomerCode()]);
            $pendingIds = $this->pendingIds($orders[0]->getCustomerCode());

            if (isset($data['expectedAdjustmentIds']) && $data['expectedAdjustmentIds'] !== $pendingIds) {
                throw new BusinessException('As deduções mudaram. Refaça a simulação.', 409);
            }
            $adjustmentIds = array_values(array_unique([...$adjustmentIds, ...$pendingIds]));
            $adjustments = [];
            foreach ($adjustmentIds as $id) {
                $adjustment = $this->em->find(Adjustment::class, $id, LockMode::PESSIMISTIC_WRITE);

                if (!$adjustment || $adjustment->getCommission() || $adjustment->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                    throw new BusinessException('Dedução indisponível ou de outro cliente.', 409);
                }
                $adjustments[] = $adjustment;
            }
            $this->winthor->assertEligible(array_map(static fn (OrderSnapshot $o): string => $o->getOrderNumber(), $orders));
            $calculation = $this->calculator->calculate($orders, $rule, isset($data['mode']) ? Input::text($data, 'mode', 10) : 'normal');
            $money = Money::net($calculation['grossAmount'], array_map(static fn ($a) => $a->getAmount(), $adjustments));
            $commission->setCreatedBy($actor)->setCustomerCode($orders[0]->getCustomerCode())->setCustomerName($orders[0]->getCustomerName())
                ->setGrossAmount($money['gross'])->setDeductions($money['deductions'])->setNetAmount($money['net'])
                ->setCalculation([...$calculation, 'reason' => $reason, 'adjustmentIds' => $adjustmentIds]);
            foreach ($orders as $order) {
                $commission->addOrder($order);
            }
            foreach ($adjustments as $a) {
                $a->setCommission($commission);
            }
            $this->em->persist($commission);
            $this->audit->record($actor, 'commission.created', $commission->getCode(), ['orderIds' => $orderIds, 'calculation' => $commission->getCalculation(), 'netAmount' => $money['net']]);

            return $commission;
        });
    }

    public function preview(array $data, User $actor): array
    {
        $orders = [];
        foreach (Input::ids($data, 'orderIds') as $id) {
            $order = $this->em->find(OrderSnapshot::class, $id);

            if (!$order || $order->getCommission() || ($orders && $order->getCustomerCode() !== $orders[0]->getCustomerCode())) {
                throw new BusinessException('Pedidos indisponíveis ou de clientes diferentes.', 409);
            }
            $orders[] = $order;
        }
        $this->cancellations->sync($orders[0]->getCustomerCode(), $actor);
        $pendingIds = $this->pendingIds($orders[0]->getCustomerCode());
        $amounts = [];
        foreach (array_unique([...Input::ids($data, 'adjustmentIds', false), ...$pendingIds]) as $id) {
            $adjustment = $this->em->find(Adjustment::class, $id);

            if (!$adjustment || $adjustment->getCommission() || $adjustment->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                throw new BusinessException('Dedução indisponível ou de outro cliente.', 409);
            }
            $amounts[] = $adjustment->getAmount();
        }
        $this->winthor->assertEligible(array_map(static fn (OrderSnapshot $o): string => $o->getOrderNumber(), $orders));
        $calculation = $this->calculator->calculate($orders, $this->rules->current(), isset($data['mode']) ? Input::text($data, 'mode', 10) : 'normal');

        return ['adjustmentIds' => $pendingIds, 'calculation' => $calculation, ...Money::net($calculation['grossAmount'], $amounts)];
    }

    public function approve(int $id, User $actor): Commission
    {
        return $this->em->wrapInTransaction(function () use ($id, $actor) {
            $c = $this->locked($id);

            if ('pending' !== $c->getStatus()) {
                throw new BusinessException('Comissão não está pendente.', 409);
            }
            $c->setStatus('approved')->setApprovedBy($actor)->setApprovedAt(new DateTimeImmutable());
            $this->audit->record($actor, 'commission.approved', $c->getCode());

            return $c;
        });
    }

    public function linkWinthor(int $id, array $data, User $actor): Commission
    {
        $recnum = Input::text($data, 'recnum', 18);
        $details = $this->payments->fetch($recnum);

        return $this->em->wrapInTransaction(function () use ($id, $recnum, $details, $actor) {
            $c = $this->locked($id);

            if ('paid' !== $c->getStatus()) {
                throw new BusinessException('Confirme o pagamento antes de vincular o RECNUM.', 409);
            }
            $payment = $this->em->getRepository(PaymentLink::class)->findOneBy(['commission' => $c]);

            if (!$payment) {
                throw new BusinessException('Registro do pagamento não encontrado.', 409);
            }
            $payment->linkWinthor($recnum, $details);
            $this->audit->record($actor, 'payment.winthor_linked', $c->getCode(), ['routine' => '749', 'recnum' => $recnum, 'verification' => $payment->getVerification()]);

            return $c;
        });
    }

    private function pendingIds(string $customer): array
    {
        return array_map(static fn (Adjustment $a): int => $a->getId(), $this->em->getRepository(Adjustment::class)->findBy(['customerCode' => $customer, 'commission' => null], ['id' => 'ASC']));
    }

    private function locked(int $id): Commission
    {
        return $this->em->find(Commission::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw new BusinessException('Comissão não encontrada.', 404);
    }
}
