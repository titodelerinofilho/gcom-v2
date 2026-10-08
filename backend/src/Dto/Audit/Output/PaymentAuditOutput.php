<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class PaymentAuditOutput
{
    /** @param list<PaymentAuditDifferenceOutput> $differences */
    public function __construct(public string $recnum, public string $commissionAmount, public string $confirmedAmount, public ?string $launchAmount, public ?string $paidAmount, public string $source, public ?string $notice, public array $differences)
    {
    }
}
