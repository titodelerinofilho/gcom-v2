<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

use App\Entity\Commission\Commission;

final readonly class PaidCommissionAuditSummaryOutput
{
    public int $id;

    public string $code;

    public string $customerCode;

    public string $customerName;

    public string $netAmount;

    public string $mode;

    public function __construct(Commission $commission)
    {
        $this->id = $commission->getId();
        $this->code = $commission->getCode();
        $this->customerCode = $commission->getCustomerCode();
        $this->customerName = $commission->getCustomerName();
        $this->netAmount = $commission->getNetAmount();
        $this->mode = $commission->getCalculation()['mode'] ?? 'normal';
    }
}
