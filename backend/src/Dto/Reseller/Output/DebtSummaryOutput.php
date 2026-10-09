<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class DebtSummaryOutput
{
    public function __construct(
        public int $openTitles,
        public string $openAmount,
        public int $overdueTitles,
        public string $overdueAmount,
        public string $ownOverdueAmount,
        public string $linkedOverdueAmount,
        public int $maxLateDays,
    ) {
    }
}
