<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Output;

final readonly class AvailableOrderOutput
{
    public function __construct(
        public string $orderNumber,
        public string $customerCode,
        public string $customerName,
        public string $orderDate,
        public string $branch,
        public string $total,
        public ?PriceContextOutput $priceContext,
        public ?string $priceContextError,
        public ?string $invoiceNumber,
    ) {
    }
}
