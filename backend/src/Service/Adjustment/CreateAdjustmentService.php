<?php

declare(strict_types=1);

namespace App\Service\Adjustment;

use App\Dto\Adjustment\Input\CreateAdjustmentInput;
use App\Dto\Adjustment\Output\AdjustmentOutput;
use App\Entity\Adjustment\Adjustment;
use App\Entity\User\User;
use App\Repository\Adjustment\AdjustmentRepository;
use App\Service\Audit\AuditRecorderService;
use App\Service\Finance\MoneyService;

final readonly class CreateAdjustmentService
{
    public function __construct(
        private AdjustmentRepository $repository,
        private AuditRecorderService $audit,
    ) {
    }

    public function create(CreateAdjustmentInput $input, User $actor): AdjustmentOutput
    {
        $adjustment = new Adjustment()
            ->setCustomerCode($input->customerCode)
            ->setType($input->type)
            ->setAmount(MoneyService::positive($input->amount))
            ->setReason($input->reason)
            ->setSourceReference($input->sourceReference)
            ->setCreatedBy($actor);

        $this->repository->save(function () use ($adjustment, $actor): void {
            $this->repository->lockCustomer($adjustment->getCustomerCode());
            $this->repository->store($adjustment);
            $this->audit->record($actor, 'adjustment.created', 'customer:'.$adjustment->getCustomerCode(), [
                'type' => $adjustment->getType(),
                'amount' => $adjustment->getAmount(),
                'reference' => $adjustment->getSourceReference(),
            ]);
        });

        return new AdjustmentOutput($adjustment);
    }
}
