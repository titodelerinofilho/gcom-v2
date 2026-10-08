<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Input\CreateCommissionInput;
use App\Dto\Commission\Output\CommissionOutput;
use App\Entity\Commission\Commission;
use App\Entity\Order\OrderSnapshot;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Repository\Commission\CommissionRepository;
use App\Repository\Order\OrderSnapshotRepository;
use App\Service\Adjustment\CancellationSynchronizerService;
use App\Service\Audit\AuditRecorderService;
use App\Service\CommissionRule\GetCurrentCommissionRuleService;
use App\Service\Finance\MoneyService;

final readonly class CreateCommissionService
{
    public function __construct(
        private CommissionRepository $commissions,
        private AuditRecorderService $audit,
        private GetCurrentCommissionRuleService $rules,
        private CommissionCalculatorService $calculator,
        private OrderGatewayInterface $winthor,
        private CancellationSynchronizerService $cancellations,
        private AdjustmentRepository $adjustments,
        private OrderSnapshotRepository $orders,
    ) {
    }

    public function create(CreateCommissionInput $input, User $actor): CommissionOutput
    {
        $orderIds = $input->orderIds;
        sort($orderIds);
        $adjustmentIds = $input->adjustmentIds;
        $first = $this->orders->find($orderIds[0]);

        if (null !== $first) {
            $this->cancellations->sync($first->getCustomerCode(), $actor);
        }

        $reason = $input->reason;
        $commission = $this->commissions->save(function () use ($orderIds, $adjustmentIds, $input, $reason, $actor) {
            $rule = $this->rules->current();

            if ($input->ruleVersion !== $rule['version']) {
                throw new BusinessException('A regra de cálculo mudou. Refaça a simulação antes de gerar a comissão.', 409);
            }

            $commission = new Commission();
            $orders = [];

            foreach ($orderIds as $id) {
                $order = $this->orders->findLocked($id);

                if (null === $order) {
                    throw new BusinessException('Pedido não encontrado.', 404);
                }

                if (null !== $order->getCommission()) {
                    throw new BusinessException('Pedido já tem comissão.', 409);
                }

                if ([] !== $orders && $order->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                    throw new BusinessException('Os pedidos devem pertencer ao mesmo cliente.');
                }

                $orders[] = $order;
            }

            $this->adjustments->lockCustomer($orders[0]->getCustomerCode());
            $pendingIds = $this->adjustments->pendingIds($orders[0]->getCustomerCode());

            if (null !== $input->expectedAdjustmentIds && $input->expectedAdjustmentIds !== $pendingIds) {
                throw new BusinessException('As deduções mudaram. Refaça a simulação.', 409);
            }

            $adjustmentIds = array_values(array_unique([...$adjustmentIds, ...$pendingIds]));

            sort($adjustmentIds);

            $adjustments = [];

            foreach ($adjustmentIds as $id) {
                $adjustment = $this->adjustments->findLocked($id);

                if (null === $adjustment || null !== $adjustment->getCommission() || $adjustment->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                    throw new BusinessException('Dedução indisponível ou de outro cliente.', 409);
                }

                $adjustments[] = $adjustment;
            }

            $this->winthor->assertEligible(array_map(static fn (OrderSnapshot $order): string => $order->getOrderNumber(), $orders));

            $calculation = $this->calculator->calculate($orders, $rule, $input->mode);

            $money = MoneyService::net($calculation['grossAmount'], array_map(static fn ($adjustment) => $adjustment->getAmount(), $adjustments));

            $commission->setCreatedBy($actor)->setCustomerCode($orders[0]->getCustomerCode())->setCustomerName($orders[0]->getCustomerName())->setGrossAmount($money['gross'])->setDeductions($money['deductions'])->setNetAmount($money['net'])->setCalculation([...$calculation, 'reason' => $reason, 'adjustmentIds' => $adjustmentIds]);

            foreach ($orders as $order) {
                $commission->addOrder($order);
            }

            foreach ($adjustments as $adjustment) {
                $adjustment->setCommission($commission);
            }

            $this->commissions->store($commission);
            $this->audit->record($actor, 'commission.created', $commission->getCode(), ['orderIds' => $orderIds, 'calculation' => $commission->getCalculation(), 'netAmount' => $money['net']]);

            return $commission;
        });

        return new CommissionOutput($commission, true);
    }
}
