<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class ActivityOutput
{
    public function __construct(
        public int $soldOrders,
        public string $salesAmount,
        public int $linkedCustomers,
        public int $activeCustomers,
        public int $cancelledOrders,
        public string $cancelledAmount,
        public string $returnedAmount,
    ) {
    }
}
