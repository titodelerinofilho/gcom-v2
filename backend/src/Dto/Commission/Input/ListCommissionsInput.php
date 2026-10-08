<?php

declare(strict_types=1);

namespace App\Dto\Commission\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCommissionsInput
{
    #[Assert\Choice(choices: ['pending', 'approved', 'paid'], message: 'Status inválido.')]
    public ?string $status;

    #[Assert\Positive]
    public int $page;

    public function __construct(
        ?string $status = null,
        int $page = 1,
    ) {
        $this->status = null === $status || '' === trim($status) ? null : trim($status);
        $this->page = min(100000, max(1, $page));
    }
}
