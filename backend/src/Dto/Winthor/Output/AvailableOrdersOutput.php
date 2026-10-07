<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Output;

final readonly class AvailableOrdersOutput
{
    /** @param list<AvailableOrderOutput> $items */
    public function __construct(public array $items)
    {
    }
}
