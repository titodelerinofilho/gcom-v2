<?php

declare(strict_types=1);

namespace App\Dto\Order\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListOrdersInput
{
    public ?string $customer;

    public bool $available;

    #[Assert\Positive]
    public int $page;

    public function __construct(
        ?string $customer = null,
        bool $available = false,
        int $page = 1,
    ) {
        $this->customer = null === $customer || '' === trim($customer) ? null : trim($customer);
        $this->available = $available;
        $this->page = min(100000, max(1, $page));
    }
}
