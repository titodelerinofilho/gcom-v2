<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ReportMonthOutput
{
    public string $month;

    public string $amount;

    public function __construct(array $row)
    {
        $this->month = (string) $row['month'];
        $this->amount = (string) $row['amount'];
    }
}
