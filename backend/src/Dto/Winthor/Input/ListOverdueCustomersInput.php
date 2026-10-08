<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListOverdueCustomersInput
{
    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D', message: 'Identificador inválido: customer.')]
    public string $customer;

    public function __construct(
        string $customer,
    ) {
        $this->customer = trim($customer);
    }
}
