<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class RatingComponentOutput
{
    public function __construct(
        public string $label,
        public int $points,
        public int $maximum,
        public string $detail,
    ) {
    }
}
