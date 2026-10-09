<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class RatingOutput
{
    /** @param list<RatingComponentOutput> $components */
    public function __construct(
        public string $tier,
        public string $label,
        public int $score,
        public string $confidence,
        public ?string $cancellationRate,
        public ?string $returnRate,
        public ?string $overdueRate,
        public array $components,
        public string $version = 'revenda-v1',
    ) {
    }
}
