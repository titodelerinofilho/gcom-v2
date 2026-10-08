<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

use App\Service\Finance\MoneyService;

final readonly class OverdueTitleOutput
{
    public string $customerCode;

    public string $customerName;

    public string $invoiceNumber;

    public string $transaction;

    public string $installment;

    public string $dueDate;

    public ?string $originalDueDate;

    public int $overdueDays;

    public string $collectionCode;

    public string $amount;

    public function __construct(array $row)
    {
        $this->customerCode = (string) $row['CODCLI'];
        $this->customerName = (string) ($row['CLIENTE'] ?? '');
        $this->invoiceNumber = (string) ($row['NUMNOTA'] ?? '');
        $this->transaction = (string) $row['NUMTRANSVENDA'];
        $this->installment = (string) $row['PREST'];
        $this->dueDate = (string) $row['DUE_DATE'];
        $this->originalDueDate = $row['ORIGINAL_DUE_DATE'] ?? null;
        $this->overdueDays = (int) $row['ATRASO'];
        $this->collectionCode = (string) $row['CODCOB'];
        $this->amount = MoneyService::normalize((string) $row['VALOR']);
    }
}
