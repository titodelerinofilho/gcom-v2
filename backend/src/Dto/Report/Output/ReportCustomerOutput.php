<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ReportCustomerOutput
{
    public string $customer_code;

    public string $customer_name;

    public int $count;

    public string $amount;

    public function __construct(array $row)
    {
        $this->customer_code = (string) $row['customer_code'];
        $this->customer_name = (string) $row['customer_name'];
        $this->count = (int) $row['count'];
        $this->amount = (string) $row['amount'];
    }
}
