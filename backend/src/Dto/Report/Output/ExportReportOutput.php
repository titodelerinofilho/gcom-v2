<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ExportReportOutput
{
    public function __construct(public string $path, public string $contentType, public string $filename, public ?string $reportId = null)
    {
    }
}
