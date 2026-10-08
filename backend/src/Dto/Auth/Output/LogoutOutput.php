<?php

declare(strict_types=1);

namespace App\Dto\Auth\Output;

final readonly class LogoutOutput
{
    public function __construct(public bool $ok)
    {
    }
}
