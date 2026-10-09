<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class CancellationOutput
{
    public function __construct(
        public string $orderNumber,
        public string $customerCode,
        public string $customerName,
        public string $cancelledAt,
        public string $amount,
        public string $reason,
        public string $source,
    ) {
    }
}
