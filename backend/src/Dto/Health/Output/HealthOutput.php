<?php

declare(strict_types=1);

namespace App\Dto\Health\Output;

final readonly class HealthOutput
{
    public function __construct(public string $status)
    {
    }
}
