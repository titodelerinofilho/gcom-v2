<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class PaymentAuditDifferenceOutput
{
    public function __construct(public string $label, public string $expected, public string $actual, public string $difference)
    {
    }
}
