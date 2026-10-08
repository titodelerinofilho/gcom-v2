<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class PreviewCommissionOutput
{
    /**
     * @param list<int>                                         $adjustmentIds
     * @param list<\App\Dto\Adjustment\Output\AdjustmentOutput> $adjustments
     */
    public function __construct(
        public array $adjustmentIds,
        public array $calculation,
        public string $gross,
        public string $deductions,
        public string $net,
        public array $adjustments,
        public CommissionChecksOutput $checks,
    ) {
    }
}
