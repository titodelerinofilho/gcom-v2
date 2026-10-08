<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckCommissionObligationsInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D')]
    public string $customerCode;

    #[Assert\Choice(choices: ['normal', 'atg'])]
    public string $mode;

    public function __construct(string $customerCode, string $mode = 'normal')
    {
        $this->customerCode = trim($customerCode);
        $this->mode = trim($mode);
    }
}
