<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Report\Input\ExportReportInput;
use App\Dto\Report\Output\ExportReportOutput;
use App\Entity\User\User;
use App\Repository\Audit\AuditEventRepository;
use App\Service\Audit\AuditRecorderService;
use Throwable;

final readonly class ExportReportService
{
    public function __construct(
        private ReportExporterService $exporter,
        private ReportCriteriaService $criteria,
        private AuditRecorderService $audit,
        private AuditEventRepository $auditEvents,
    ) {
    }

    public function export(string $kind, string $format, ExportReportInput $input, User $actor): ExportReportOutput
    {
        [$from, $to] = $this->criteria->range($input);
        $filters = $this->criteria->filters($input, $kind);
        $path = $this->exporter->generate($format, $from, $to, $filters, $kind);

        try {
            $this->audit->record($actor, 'report.exported', $kind, ['format' => $format, 'from' => $from, 'toExclusive' => $to, 'filters' => $filters]);
            $this->auditEvents->savePending();
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        $contentType = ['csv' => 'text/csv; charset=utf-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];

        return new ExportReportOutput($path, $contentType, 'gcom-'.$kind.'.'.$format);
    }
}
