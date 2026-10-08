<?php

declare(strict_types=1);

namespace App\Dto\Report\Input;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ReportQueryInput
{
    #[Assert\Date]
    public string $from;

    #[Assert\Date]
    public string $to;

    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D')]
    public ?string $customer;

    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D')]
    public ?string $orderNumber;

    #[Assert\Choice(choices: ['pending', 'approved', 'paid', 'rejected'])]
    public ?string $status;

    #[Assert\Choice(choices: ['normal', 'atg'])]
    public ?string $mode;

    #[Assert\Choice(choices: ['debt', 'return', 'cancellation'])]
    public ?string $type;

    #[Assert\Choice(choices: ['pending', 'deducted'])]
    public ?string $state;

    #[Assert\Choice(choices: ['created', 'paid', 'applied'])]
    public ?string $dateBasis;

    #[Assert\Positive]
    public int $page;

    public function __construct(
        ?string $from = null,
        ?string $to = null,
        ?string $customer = null,
        ?string $orderNumber = null,
        ?string $status = null,
        ?string $mode = null,
        ?string $type = null,
        ?string $state = null,
        ?string $dateBasis = null,
        int $page = 1,
    ) {
        $this->from = $from ?? date('Y-01-01');
        $this->to = $to ?? date('Y-m-d');
        $this->customer = null === $customer || '' === trim($customer) ? null : trim($customer);
        $this->orderNumber = null === $orderNumber || '' === trim($orderNumber) ? null : trim($orderNumber);
        $this->status = null === $status || '' === trim($status) ? null : trim($status);
        $this->mode = null === $mode || '' === trim($mode) ? null : trim($mode);
        $this->type = null === $type || '' === trim($type) ? null : trim($type);
        $this->state = null === $state || '' === trim($state) ? null : trim($state);
        $this->dateBasis = null === $dateBasis || '' === trim($dateBasis) ? null : trim($dateBasis);
        $this->page = min(100000, max(1, $page));
    }
}
