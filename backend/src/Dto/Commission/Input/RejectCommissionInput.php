<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RejectCommissionInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 2000)]
    public string $reason;

    public function __construct(string $reason)
    {
        $this->reason = trim($reason);
    }
}
