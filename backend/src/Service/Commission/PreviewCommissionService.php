<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Dto\Commission\Input\PreviewCommissionInput;
use App\Dto\Commission\Output\PreviewCommissionOutput;
use App\Entity\Order\OrderSnapshot;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Integration\Winthor\OrderGatewayInterface;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Repository\Order\OrderSnapshotRepository;
use App\Service\Adjustment\CancellationSynchronizerService;
use App\Service\CommissionRule\GetCurrentCommissionRuleService;
use App\Service\Finance\MoneyService;

final readonly class PreviewCommissionService
{
    public function __construct(
        private GetCurrentCommissionRuleService $rules,
        private CommissionCalculatorService $calculator,
        private OrderGatewayInterface $winthor,
        private CancellationSynchronizerService $cancellations,
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

        $this->cancellations->sync($orders[0]->getCustomerCode(), $actor);

        $pendingIds = $this->adjustments->pendingIds($orders[0]->getCustomerCode());

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

        $this->winthor->assertEligible(array_map(static fn (OrderSnapshot $order): string => $order->getOrderNumber(), $orders));

        $calculation = $this->calculator->calculate($orders, $this->rules->current(), $input->mode);

        $money = MoneyService::net($calculation['grossAmount'], $amounts);

        return new PreviewCommissionOutput($pendingIds, $calculation, $money['gross'], $money['deductions'], $money['net'], $adjustmentOutputs);
    }
}
