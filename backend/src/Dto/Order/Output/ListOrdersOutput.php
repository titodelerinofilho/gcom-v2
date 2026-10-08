<?php

declare(strict_types=1);

namespace App\Dto\Order\Output;

final readonly class ListOrdersOutput
{
    /** @param list<OrderOutput> $items */
    public function __construct(public array $items, public int $total, public int $page)
    {
    }
}
