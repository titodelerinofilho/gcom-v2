<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Input\RejectCommissionInput;
use App\Dto\Commission\Output\CommissionOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Repository\Commission\CommissionRepository;
use App\Repository\Commission\PaymentLinkRepository;
use App\Service\Audit\AuditRecorderService;

final readonly class RejectCommissionService
{
    public function __construct(
        private CommissionRepository $commissions,
        private AdjustmentRepository $adjustments,
        private PaymentLinkRepository $payments,
        private AuditRecorderService $audit,
    ) {
    }

    public function reject(int $id, RejectCommissionInput $input, User $actor): CommissionOutput
    {
        $commission = $this->commissions->save(function () use ($id, $input, $actor) {
            $commission = $this->commissions->locked($id);

            if (false === in_array($commission->getStatus(), ['pending', 'approved'], true) || null !== $this->payments->findForCommission($commission)) {
                throw new BusinessException('Somente comissões pendentes ou aprovadas sem pagamento podem ser reprovadas.', 409);
            }

            $this->adjustments->lockCustomer($commission->getCustomerCode());
            $orders = $commission->getOrders()->toArray();
            $adjustments = $this->adjustments->findForCommission($commission);
            $commission->reject($input->reason, $actor, $orders, $adjustments);
            $this->commissions->releaseRejectedAssignments($commission, $orders, $adjustments);
            $this->audit->record($actor, 'commission.rejected', $commission->getCode(), ['reason' => $input->reason, 'orderIds' => array_map(static fn ($order): int => $order->getId(), $orders), 'adjustmentIds' => array_map(static fn ($adjustment): int => $adjustment->getId(), $adjustments)]);

            return $commission;
        });

        return new CommissionOutput($commission, true);
    }
}
