<?php

declare(strict_types=1);

namespace App\Dto\Report\Output;

final readonly class ReportFiltersOutput
{
    public ?string $customer;

    public ?string $orderNumber;

    public ?string $status;

    public ?string $mode;

    public ?string $type;

    public ?string $state;

    public ?string $dateBasis;

    public function __construct(array $filters)
    {
        $this->customer = $filters['customer'] ?? null;
        $this->orderNumber = $filters['orderNumber'] ?? null;
        $this->status = $filters['status'] ?? null;
        $this->mode = $filters['mode'] ?? null;
        $this->type = $filters['type'] ?? null;
        $this->state = $filters['state'] ?? null;
        $this->dateBasis = $filters['dateBasis'] ?? null;
    }
}
