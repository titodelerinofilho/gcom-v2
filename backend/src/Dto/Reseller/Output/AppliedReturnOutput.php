<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class AppliedReturnOutput
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $amount,
        public string $appliedAt,
        public int $commissionId,
        public string $commissionCode,
        public string $commissionStatus,
        public string $reason,
    ) {
    }
}
