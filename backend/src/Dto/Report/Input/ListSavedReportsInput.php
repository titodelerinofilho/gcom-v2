<?php

declare(strict_types=1);

namespace App\Dto\Report\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListSavedReportsInput
{
    public function __construct(#[Assert\Range(min: 1, max: 100000)] public int $page = 1)
    {
    }
}
