<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class DebtOutput
{
    public function __construct(
        public string $customerCode,
        public string $customerName,
        public bool $own,
        public string $transaction,
        public string $installment,
        public ?string $invoice,
        public ?string $dueDate,
        public int $daysLate,
        public string $amount,
    ) {
    }
}
