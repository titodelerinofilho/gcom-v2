<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Input;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetResellerProfileInput
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $from;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $to;

    public function __construct(
        #[Assert\Regex(pattern: '/^[1-9][0-9]{0,17}$/D')]
        #[Assert\NotBlank]
        public string $customer,
        ?string $from = null,
        ?string $to = null,
        #[Assert\Range(min: 1, max: 100000)]
        public int $paidPage = 1,
        #[Assert\Range(min: 1, max: 100000)]
        public int $returnsPage = 1,
        #[Assert\Range(min: 1, max: 100000)]
        public int $debtsPage = 1,
        #[Assert\Range(min: 1, max: 100000)]
        public int $cancellationsPage = 1,
    ) {
        $this->from = $from ?? new DateTimeImmutable('first day of this month')->modify('-11 months')->format('Y-m-d');
        $this->to = $to ?? date('Y-m-d');
    }
}
