<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class ProfileMonthOutput
{
    public function __construct(
        public string $month,
        public int $orders,
        public string $salesAmount,
        public string $generatedAmount,
        public string $paidAmount,
    ) {
    }
}
