<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Input\GetReportSummaryInput;
use App\Dto\Report\Output\GetReportSummaryOutput;
use App\Repository\Report\ReportRepository;

final readonly class GetReportSummaryService
{
    public function __construct(
        private ReportRepository $repository,
        private ReportCriteriaService $criteria,
    ) {
    }

    public function get(GetReportSummaryInput $input): GetReportSummaryOutput
    {
        [$from, $to] = $this->criteria->range($input);

        return new GetReportSummaryOutput($this->repository->summary($from, $to));
    }
}
