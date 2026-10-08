<?php

declare(strict_types=1);

namespace App\Dto\Adjustment\Output;

final readonly class ListAdjustmentsOutput
{
    /** @param list<AdjustmentOutput> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
    ) {
    }
}
