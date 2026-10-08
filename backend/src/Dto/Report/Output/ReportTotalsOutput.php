<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ReportTotalsOutput
{
    public int $count;

    public string $gross;

    public string $deductions;

    public string $paid;

    public string $outstanding;

    public function __construct(array $row)
    {
        $this->count = (int) $row['count'];
        $this->gross = (string) $row['gross'];
        $this->deductions = (string) $row['deductions'];
        $this->paid = (string) $row['paid'];
        $this->outstanding = (string) $row['outstanding'];
    }
}
