<?php

declare(strict_types=1);

namespace App\Dto\Report\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetReportSummaryInput
{
    #[Assert\Date]
    public string $from;

    #[Assert\Date]
    public string $to;

    public function __construct(
        ?string $from = null,
        ?string $to = null,
    ) {
        $this->from = $from ?? date('Y-01-01');
        $this->to = $to ?? date('Y-m-d');
    }
}
