<?php

declare(strict_types=1);

namespace App\Dto\CommissionRule\Output;

final readonly class ListCommissionRuleHistoryOutput
{
    /** @param list<CommissionRuleOutput> $items */
    public function __construct(public array $items, public int $total, public int $page)
    {
    }
}
