<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class GetReportSummaryOutput
{
    public ReportTotalsOutput $totals;

    /** @var list<ReportStatusOutput> */
    public array $byStatus;

    /** @var list<ReportCustomerOutput> */
    public array $byCustomer;

    /** @var list<ReportMonthOutput> */
    public array $monthly;

    public function __construct(array $summary)
    {
        $this->totals = new ReportTotalsOutput($summary['totals']);
        $this->byStatus = array_map(static fn (array $row): ReportStatusOutput => new ReportStatusOutput($row), $summary['byStatus']);
        $this->byCustomer = array_map(static fn (array $row): ReportCustomerOutput => new ReportCustomerOutput($row), $summary['byCustomer']);
        $this->monthly = array_map(static fn (array $row): ReportMonthOutput => new ReportMonthOutput($row), $summary['monthly']);
    }
}
