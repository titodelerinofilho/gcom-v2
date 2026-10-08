<?php

declare(strict_types=1);

namespace App\Service\Report\Export;

use App\Dto\Report\Output\ReportExportContext;
use App\Service\Report\ReportRowsService;

final readonly class CsvExportStrategy implements ReportFormatStrategyInterface
{
    public function __construct(
        private ReportRowsService $rows,
    ) {
    }

    public function write(ReportExportContext $context): void
    {
        $path = $context->path;
        $columns = $context->columns;

        $out = fopen($path, 'w');

        try {
            fwrite($out, '﻿');
            fputcsv($out, ['Relatório', $context->title], ';', '"', '');
            fputcsv($out, ['Gerado em', $context->generatedAt], ';', '"', '');
            fputcsv($out, ['Dados preservados em', $context->savedAt ?? $context->generatedAt], ';', '"', '');
            fputcsv($out, array_values($columns), ';', '"', '');

            foreach ($this->rows->rows($context) as $row) {
                $values = array_map(static fn ($value) => 1 === preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'".$value : $value, array_values($row));
                fputcsv($out, $values, ';', '"', '');
            }
        } finally {
            fclose($out);
        }
    }
}
