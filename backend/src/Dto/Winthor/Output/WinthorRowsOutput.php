<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Output;

final readonly class WinthorRowsOutput
{
    /** @param list<WinthorRowOutput> $items */
    public function __construct(public array $items)
    {
    }
}
