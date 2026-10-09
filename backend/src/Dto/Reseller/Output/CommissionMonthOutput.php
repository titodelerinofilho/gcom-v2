<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class CommissionMonthOutput
{
    public function __construct(
        public string $month,
        public string $generatedAmount,
        public string $paidAmount,
    ) {
    }
}
