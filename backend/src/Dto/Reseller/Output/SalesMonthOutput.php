<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class SalesMonthOutput
{
    public function __construct(
        public string $month,
        public int $orders,
        public string $salesAmount,
    ) {
    }
}
