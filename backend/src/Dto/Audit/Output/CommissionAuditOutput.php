<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

use App\Dto\Commission\Output\PaymentOutput;

final readonly class CommissionAuditOutput
{
    /** @param list<OrderAuditOutput> $orders
     * @param list<AuditOutput> $events
     */
    public function __construct(public PaidCommissionAuditSummaryOutput $commission, public ?PaymentOutput $payment, public string $checkedAt, public array $orders, public array $events, public ?PaymentAuditOutput $paymentCheck = null)
    {
    }
}
