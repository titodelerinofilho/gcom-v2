<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Input\ListSavedReportsInput;
use App\Dto\Report\Output\ListSavedReportsOutput;
use App\Dto\Report\Output\SavedReportOutput;
use App\Entity\Report\ReportSnapshot;
use App\Repository\Report\ReportSnapshotRepository;

final readonly class ListSavedReportsService
{
    public function __construct(private ReportSnapshotRepository $snapshots)
    {
    }

    public function list(ListSavedReportsInput $input): ListSavedReportsOutput
    {
        $result = $this->snapshots->findPage($input->page);

        return new ListSavedReportsOutput(array_map(static fn (ReportSnapshot $snapshot): SavedReportOutput => new SavedReportOutput($snapshot), $result['items']), $result['total'], $input->page);
    }
}
