<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Output;

use JsonSerializable;

final readonly class WinthorRowOutput implements JsonSerializable
{
    /** @param array<string, scalar|null> $values */
    public function __construct(private array $values)
    {
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
