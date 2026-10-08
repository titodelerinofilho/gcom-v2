<?php

declare(strict_types=1);

namespace App\Service\Report\Export;

use App\Dto\Report\Output\ReportExportContext;

interface ReportFormatStrategyInterface
{
    public function write(ReportExportContext $context): void;
}
