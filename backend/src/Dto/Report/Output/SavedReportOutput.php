<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

use App\Entity\Report\ReportSnapshot;
use DateTimeImmutable;

final readonly class SavedReportOutput
{
    public string $id;

    public string $kind;

    public string $from;

    public string $to;

    public ReportFiltersOutput $filters;

    public int $recordCount;

    public string $createdAt;

    public string $createdBy;

    public string $url;

    public function __construct(ReportSnapshot $snapshot)
    {
        $this->id = $snapshot->getId();
        $this->kind = $snapshot->getKind();
        $this->from = $snapshot->getDateFrom();
        $this->to = (new DateTimeImmutable($snapshot->getDateToExclusive()))->modify('-1 day')->format('Y-m-d');
        $this->filters = new ReportFiltersOutput($snapshot->getFilters());
        $this->recordCount = count($snapshot->getRows());
        $this->createdAt = $snapshot->getCreatedAt()->format(\DATE_ATOM);
        $this->createdBy = $snapshot->getCreatedBy()->getName();
        $this->url = '/reports/history/'.$this->id;
    }
}
