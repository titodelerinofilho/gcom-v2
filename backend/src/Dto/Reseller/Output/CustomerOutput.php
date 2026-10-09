<?php

declare(strict_types=1);

namespace App\Dto\Reseller\Output;

final readonly class CustomerOutput
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
