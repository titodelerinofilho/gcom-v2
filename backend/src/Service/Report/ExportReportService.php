<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Input\ExportReportInput;
use App\Dto\Report\Output\ExportReportOutput;
use App\Entity\Report\ReportSnapshot;
use App\Entity\User\User;
use App\Repository\Report\ReportRepository;
use App\Repository\Report\ReportSnapshotRepository;
use App\Service\Audit\AuditRecorderService;
use DateTimeZone;
use Throwable;

final readonly class ExportReportService
{
    public function __construct(
        private ReportExporterService $exporter,
        private ReportCriteriaService $criteria,
        private AuditRecorderService $audit,
        private ReportRepository $reports,
        private ReportSnapshotRepository $snapshots,
        private ReportFilenameService $filenames,
    ) {
    }

    public function export(string $kind, string $format, ExportReportInput $input, User $actor): ExportReportOutput
    {
        [$from, $to] = $this->criteria->range($input);

        $filters = $this->criteria->filters($input, $kind);
        $rows = iterator_to_array($this->reports->export($from, $to, $filters, $kind), false);
        $snapshot = new ReportSnapshot($kind, $from, $to, $filters, $rows, $actor);
        $savedAt = $snapshot->getCreatedAt()->setTimezone(new DateTimeZone('America/Fortaleza'))->format('d/m/Y H:i:s').' (America/Fortaleza)';
        $path = $this->exporter->generate($format, $from, $to, $filters, $kind, $rows, $savedAt);

        try {
            $this->snapshots->save($snapshot, fn () => $this->audit->record($actor, 'report.exported', $snapshot->getId(), ['kind' => $kind, 'format' => $format, 'from' => $from, 'toExclusive' => $to, 'filters' => $filters, 'recordCount' => count($rows)]));
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        $contentType = ['csv' => 'text/csv; charset=utf-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];

        return new ExportReportOutput($path, $contentType, $this->filenames->create($kind, $format), $snapshot->getId());
    }
}
