<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LinkWinthorPaymentInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 18)]
    public string $recnum;

    public function __construct(
        string $recnum,
    ) {
        $this->recnum = trim($recnum);
    }
}
