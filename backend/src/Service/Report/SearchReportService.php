<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Input\SearchReportInput;
use App\Dto\Report\Output\AdjustmentReportRowOutput;
use App\Dto\Report\Output\CommissionReportRowOutput;
use App\Dto\Report\Output\SearchReportOutput;
use App\Repository\Report\ReportRepository;

final readonly class SearchReportService
{
    public function __construct(
        private ReportRepository $repository,
        private ReportCriteriaService $criteria,
    ) {
    }

    public function search(string $kind, SearchReportInput $input): SearchReportOutput
    {
        [$from, $to] = $this->criteria->range($input);
        $filters = $this->criteria->filters($input, $kind);
        $result = $this->repository->search($from, $to, $filters, $kind, $input->page);
        $items = array_map(static fn (array $row): CommissionReportRowOutput|AdjustmentReportRowOutput => 'commissions' === $kind ? new CommissionReportRowOutput($row) : new AdjustmentReportRowOutput($row), $result['items']);

        return new SearchReportOutput($items, $result['total'], $result['amount'], $result['page']);
    }
}
