<?php

declare(strict_types=1);

namespace App\Dto\Commission\Output;

final readonly class ConfirmedPaymentOutput
{
    public function __construct(public int $id, public string $status)
    {
    }
}
