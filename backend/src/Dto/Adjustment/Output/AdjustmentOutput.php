<?php

declare(strict_types=1);

namespace App\Dto\Adjustment\Output;

use App\Entity\Adjustment\Adjustment;

final readonly class AdjustmentOutput
{
    public ?int $id;

    public string $customerCode;

    public string $type;

    public string $amount;

    public string $reason;

    public string $sourceReference;

    public ?array $sourceSnapshot;

    public ?int $commissionId;

    public string $createdAt;

    public function __construct(Adjustment $adjustment, ?int $historicalCommissionId = null)
    {
        $this->id = $adjustment->getId();
        $this->customerCode = $adjustment->getCustomerCode();
        $this->type = $adjustment->getType();
        $this->amount = $adjustment->getAmount();
        $this->reason = $adjustment->getReason();
        $this->sourceReference = $adjustment->getSourceReference();
        $this->sourceSnapshot = $adjustment->getSourceSnapshot();
        $this->commissionId = $historicalCommissionId ?? $adjustment->getCommission()?->getId();
        $this->createdAt = $adjustment->getCreatedAt()->format(\DATE_ATOM);
    }
}
