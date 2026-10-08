<?php

declare(strict_types=1);

namespace App\Dto\Order\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ImportOrderInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 12)]
    public string $orderNumber;

    public function __construct(
        string $orderNumber,
    ) {
        $this->orderNumber = trim($orderNumber);
    }
}
