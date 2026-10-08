<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class PaidReturnCommissionOutput
{
    public function __construct(
        public int $commissionId,
        public string $commissionCode,
        public string $mode,
        public string $paidAt,
    ) {
    }
}
