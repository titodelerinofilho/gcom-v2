<?php

declare(strict_types=1);

namespace App\Dto\Winthor\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListWinthorOrdersInput
{
    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D', message: 'Identificador inválido: customer.')]
    public string $customer;

    #[Assert\Date]
    public string $from;

    #[Assert\Date]
    public string $to;

    #[Assert\Positive]
    public ?int $square;

    public function __construct(
        string $customer,
        string $from,
        string $to,
        ?int $square = null,
    ) {
        $this->customer = trim($customer);
        $this->from = trim($from);
        $this->to = trim($to);
        $this->square = $square;
    }
}
