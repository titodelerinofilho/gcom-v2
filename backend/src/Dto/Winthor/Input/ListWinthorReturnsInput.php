<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListWinthorReturnsInput
{
    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D', message: 'Identificador inválido: customer.')]
    public string $customer;

    public bool $atg;

    public function __construct(
        string $customer,
        bool $atg = false,
    ) {
        $this->customer = trim($customer);
        $this->atg = $atg;
    }
}
