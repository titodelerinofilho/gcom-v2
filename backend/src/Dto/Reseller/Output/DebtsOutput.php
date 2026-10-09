<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class DebtsOutput
{
    /** @param list<DebtOutput> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $pageSize)
    {
    }
}
