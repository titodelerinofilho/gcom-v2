<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class InvoiceAuditStateOutput
{
    public function __construct(public string $invoiceNumber, public ?string $cancelledAt)
    {
    }
}
