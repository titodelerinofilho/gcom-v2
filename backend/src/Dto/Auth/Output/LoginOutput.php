<?php

declare(strict_types=1);

namespace App\Dto\Auth\Output;

final readonly class LoginOutput
{
    public function __construct(public string $error)
    {
    }
}
