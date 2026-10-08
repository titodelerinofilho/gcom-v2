<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class ReturnItemOutput
{
    /** @param list<PaidReturnCommissionOutput> $paidCommissions */
    public function __construct(
        public string $orderNumber,
        public string $productCode,
        public string $description,
        public string $quantity,
        public string $paymentStatus,
        public array $paidCommissions,
    ) {
    }
}
