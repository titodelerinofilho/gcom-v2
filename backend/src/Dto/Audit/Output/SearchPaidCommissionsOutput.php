<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class SearchPaidCommissionsOutput
{
    /** @param list<PaidCommissionAuditSummaryOutput> $items */
    public function __construct(public array $items, public int $total, public int $page)
    {
    }
}
