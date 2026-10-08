<?php

declare(strict_types=1);

namespace App\Dto\Audit\Output;

final readonly class OrderAuditChangeOutput
{
    public function __construct(public string $section, public string $record, public string $field, public ?string $saved, public ?string $current)
    {
    }
}
