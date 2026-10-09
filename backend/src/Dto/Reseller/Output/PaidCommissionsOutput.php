<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class PaidCommissionsOutput
{
    /** @param list<PaidCommissionOutput> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $pageSize)
    {
    }
}
