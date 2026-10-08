<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Dto\Commission\Output\CommissionOutput;
use App\Dto\Commission\Output\PaymentOutput;
use App\Entity\Adjustment\Adjustment;
use App\Exception\Business\BusinessException;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Repository\Commission\CommissionRepository;
use App\Repository\Commission\PaymentLinkRepository;

final readonly class GetCommissionService
{
    public function __construct(
        private CommissionRepository $commissions,
        private PaymentLinkRepository $payments,
        private AdjustmentRepository $adjustments,
        private CommissionItemAllocationService $itemAllocation,
    ) {
    }

    public function get(int $id): CommissionOutput
    {
        $commission = $this->commissions->find($id) ?? throw new BusinessException('Comissão não encontrada.', 404);
        $payment = $this->payments->findForCommission($commission);
        $sources = 'rejected' === $commission->getStatus() ? $commission->getRejectedAdjustments()->toArray() : $this->adjustments->findForCommission($commission);
        $adjustments = array_map(static fn (Adjustment $adjustment): AdjustmentOutput => new AdjustmentOutput($adjustment, 'rejected' === $commission->getStatus() ? $commission->getId() : null), $sources);

        return new CommissionOutput($commission, true, null === $payment ? null : new PaymentOutput($payment), $adjustments, true, $this->itemAllocation->allocate($commission->getCalculation()));
    }
}
