<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class SearchReportOutput
{
    /** @param list<CommissionReportRowOutput|AdjustmentReportRowOutput> $items */
    public function __construct(public array $items, public int $total, public string $amount, public int $page)
    {
    }
}
