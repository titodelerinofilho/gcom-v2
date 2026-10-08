<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

use JsonSerializable;

final readonly class CommissionChecksOutput implements JsonSerializable
{
    /** @param list<OverdueTitleOutput> $overdueTitles */
    public function __construct(
        public string $customerCode,
        public string $checkedAt,
        public array $overdueTitles,
        public string $overdueTotal,
        public int $returnsFound,
        public array $returns,
        public string $fingerprint,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [...get_object_vars($this), 'returns' => array_map(static fn (ReturnCandidateOutput $item): array => get_object_vars($item), $this->returns), 'overdueTitles' => array_map(static fn (OverdueTitleOutput $title): array => get_object_vars($title), $this->overdueTitles)];
    }
}
