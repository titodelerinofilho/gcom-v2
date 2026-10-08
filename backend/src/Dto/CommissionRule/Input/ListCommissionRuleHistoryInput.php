<?php

declare(strict_types=1);

namespace App\Dto\CommissionRule\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCommissionRuleHistoryInput
{
    #[Assert\Positive]
    public int $page;

    public function __construct(
        int $page = 1,
    ) {
        $this->page = min(100000, max(1, $page));
    }
}
