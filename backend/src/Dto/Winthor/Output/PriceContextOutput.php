<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Output;

final readonly class PriceContextOutput
{
    public function __construct(
        public int $orderRegion,
        public int $psdRegion,
        public ?int $pscfRegion,
        public string $paymentPlan,
        public string $priceColumn,
        public string $normalBasis,
        public int $ruleVersion,
    ) {
    }
}
