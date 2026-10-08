<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class ReturnCandidateOutput
{
    /** @param list<string> $orderNumbers
     * @param list<ReturnItemOutput> $items
     */
    public function __construct(
        public string $transaction,
        public string $invoiceNumber,
        public string $customerCode,
        public string $customerName,
        public ?string $date,
        public array $orderNumbers,
        public ?string $deductionAmount,
        public bool $selected,
        public array $items = [],
    ) {
    }
}
