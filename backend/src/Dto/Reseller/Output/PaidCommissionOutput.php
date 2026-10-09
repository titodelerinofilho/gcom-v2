<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class PaidCommissionOutput
{
    public function __construct(
        public int $id,
        public string $code,
        public string $paidAt,
        public string $grossAmount,
        public string $deductions,
        public string $netAmount,
        public string $paidAmount,
        public bool $manualAmount,
    ) {
    }
}
