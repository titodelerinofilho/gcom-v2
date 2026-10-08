<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class OrderAuditOutput
{
    /** @param list<OrderAuditChangeOutput> $changes */
    public function __construct(public string $orderNumber, public string $invoiceNumber, public string $capturedAt, public string $status, public bool $invoiceBaselineAvailable, public array $changes, public array $currentInvoices = [])
    {
    }
}
