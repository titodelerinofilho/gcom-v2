<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ReportStatusOutput
{
    public string $status;

    public int $count;

    public string $amount;

    public function __construct(array $row)
    {
        $this->status = (string) $row['status'];
        $this->count = (int) $row['count'];
        $this->amount = (string) $row['amount'];
    }
}
