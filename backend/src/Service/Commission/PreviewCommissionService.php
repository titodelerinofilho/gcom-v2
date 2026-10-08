<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Dto\Commission\Input\PreviewCommissionInput;
use App\Dto\Commission\Output\PreviewCommissionOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Repository\Order\OrderSnapshotRepository;
use App\Service\CommissionRule\GetCurrentCommissionRuleService;
use App\Service\Finance\MoneyService;

final readonly class PreviewCommissionService
{
    public function __construct(
        private GetCurrentCommissionRuleService $rules,
        private CommissionCalculatorService $calculator,
        private ValidateCommissionOrdersService $eligibility,
        private CheckCommissionObligationsService $checks,
        private AdjustmentRepository $adjustments,
        private OrderSnapshotRepository $orders,
    ) {
    }

    public function preview(PreviewCommissionInput $input, User $actor): PreviewCommissionOutput
    {
        $orders = [];

        foreach ($input->orderIds as $id) {
            $order = $this->orders->find($id);

            if (null === $order || null !== $order->getCommission() || [] !== $orders && $order->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                throw new BusinessException('Pedidos indisponíveis ou de clientes diferentes.', 409);
            }

            $orders[] = $order;
        }

        $this->eligibility->validate($orders, $input->mode, $input->square);

        $checks = $this->checks->check($orders[0]->getCustomerCode(), $input->mode, $actor, $input->returnTransactions);

        $pendingIds = $this->adjustments->pendingIds($orders[0]->getCustomerCode(), $input->returnTransactions);

        $amounts = [];
        $adjustmentOutputs = [];

        foreach (array_unique([...$input->adjustmentIds, ...$pendingIds]) as $id) {
            $adjustment = $this->adjustments->find($id);

            if (null === $adjustment || null !== $adjustment->getCommission() || $adjustment->getCustomerCode() !== $orders[0]->getCustomerCode()) {
                throw new BusinessException('Dedução indisponível ou de outro cliente.', 409);
            }

            $amounts[] = $adjustment->getAmount();
            $adjustmentOutputs[] = new AdjustmentOutput($adjustment);
        }

        $calculation = [...$this->calculator->calculate($orders, $this->rules->current(), $input->mode, $input->square), 'checks' => $checks->jsonSerialize()];

        $money = MoneyService::net($calculation['grossAmount'], $amounts);

        return new PreviewCommissionOutput($pendingIds, $calculation, $money['gross'], $money['deductions'], $money['net'], $adjustmentOutputs, $checks);
    }
}
