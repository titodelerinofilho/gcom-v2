<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class ListCommissionsOutput
{
    /** @param list<CommissionOutput> $items */
    public function __construct(public array $items, public int $total, public int $page)
    {
    }
}
