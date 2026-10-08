<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class ListAuditOutput
{
    /** @param list<AuditOutput> $items */
    public function __construct(public array $items, public int $total, public int $page)
    {
    }
}
