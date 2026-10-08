<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ReportExportContext
{
    /**
     * @param array<string, string> $filters
     * @param array<string, string> $columns
     */
    public function __construct(
        public string $path,
        public string $from,
        public string $to,
        public array $filters,
        public string $kind,
        public array $columns,
        public string $title,
        public string $criteria,
    ) {
    }
}
