<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class ResellerFinanceOutput
{
    /** @param list<CommissionMonthOutput> $monthly */
    public function __construct(
        public FinanceSummaryOutput $summary,
        public PaidCommissionsOutput $paid,
        public AppliedReturnsOutput $returns,
        public array $monthly,
    ) {
    }
}
