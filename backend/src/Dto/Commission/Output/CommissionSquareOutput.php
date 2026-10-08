<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class CommissionSquareOutput
{
    public function __construct(
        public int $code,
        public string $name,
        public string $type,
        public int $psdSquare,
        public int $pscfSquare,
        public ?int $psdRegion,
        public ?int $pscfRegion,
    ) {
    }
}
