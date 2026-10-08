<?php

declare(strict_types=1);

namespace App\Dto\Audit\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchPaidCommissionsInput
{
    public function __construct(#[Assert\Length(max: 100)] public string $query = '', #[Assert\Range(min: 1, max: 100000)] public int $page = 1)
    {
    }
}
