<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class FinanceSummaryOutput
{
    public function __construct(
        public int $generatedCount,
        public string $grossAmount,
        public string $deductions,
        public string $netAmount,
        public string $pendingAmount,
        public int $paidCount,
        public string $paidAmount,
        public string $appliedReturnsAmount,
    ) {
    }
}
