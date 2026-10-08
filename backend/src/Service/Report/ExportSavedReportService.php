<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Output\ExportReportOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\Audit\AuditEventRepository;
use App\Repository\Report\ReportSnapshotRepository;
use App\Service\Audit\AuditRecorderService;
use DateTimeZone;
use Throwable;

final readonly class ExportSavedReportService
{
    public function __construct(private ReportSnapshotRepository $snapshots, private ReportExporterService $exporter, private AuditRecorderService $audit, private AuditEventRepository $events, private ReportFilenameService $filenames)
    {
    }

    public function export(string $id, string $format, User $actor): ExportReportOutput
    {
        $snapshot = $this->snapshots->find($id) ?? throw new BusinessException('Relatório salvo não encontrado.', 404);
        $savedAt = $snapshot->getCreatedAt()->setTimezone(new DateTimeZone('America/Fortaleza'))->format('d/m/Y H:i:s').' (America/Fortaleza)';
        $path = $this->exporter->generate($format, $snapshot->getDateFrom(), $snapshot->getDateToExclusive(), $snapshot->getFilters(), $snapshot->getKind(), $snapshot->getRows(), $savedAt);

        try {
            $this->audit->record($actor, 'report.downloaded', $id, ['format' => $format]);
            $this->events->savePending();
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        $type = ['pdf' => 'application/pdf', 'csv' => 'text/csv; charset=utf-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$format];

        return new ExportReportOutput($path, $type, $this->filenames->create($snapshot->getKind(), $format), $id);
    }
}
