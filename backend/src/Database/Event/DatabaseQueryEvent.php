<?php

declare(strict_types=1);

namespace App\Database\Event;

use Throwable;

final readonly class DatabaseQueryEvent
{
    public function __construct(
        public string $sql,
        public array $parameters,
        public float $duration,
        public string $connection,
        public string $operation = 'query',
        public ?Throwable $exception = null,
    ) {
    }
}
