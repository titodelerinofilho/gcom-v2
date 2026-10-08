<?php

declare(strict_types=1);

namespace App\Service\Commission;

use App\Dto\Commission\Output\CommissionOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Commission\CommissionRepository;
use App\Service\Audit\AuditRecorderService;
use DateTimeImmutable;

final readonly class ApproveCommissionService
{
    public function __construct(
        private AuditRecorderService $audit,
        private CommissionRepository $commissions,
    ) {
    }

    public function approve(int $id, User $actor): CommissionOutput
    {
        $commission = $this->commissions->save(function () use ($id, $actor) {
            $commission = $this->commissions->locked($id);

            if ('pending' !== $commission->getStatus()) {
                throw new BusinessException('Comissão não está pendente.', 409);
            }

            $commission->setStatus('approved')->setApprovedBy($actor)->setApprovedAt(new DateTimeImmutable());
            $this->audit->record($actor, 'commission.approved', $commission->getCode());

            return $commission;
        });

        return new CommissionOutput($commission);
    }
}
