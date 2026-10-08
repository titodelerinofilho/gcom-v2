<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Output\SavedReportOutput;
use App\Exception\Business\BusinessException;
use App\Repository\Report\ReportSnapshotRepository;

final readonly class GetSavedReportService
{
    public function __construct(private ReportSnapshotRepository $snapshots)
    {
    }

    public function get(string $id): SavedReportOutput
    {
        return new SavedReportOutput($this->snapshots->find($id) ?? throw new BusinessException('Relatório salvo não encontrado.', 404));
    }
}
