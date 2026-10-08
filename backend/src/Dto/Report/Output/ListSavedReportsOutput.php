<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ListSavedReportsOutput
{
    /** @param list<SavedReportOutput> $items */
    public function __construct(public array $items, public int $total, public int $page)
    {
    }
}
