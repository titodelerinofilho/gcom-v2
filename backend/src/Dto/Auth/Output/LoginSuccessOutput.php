<?php

declare(strict_types=1);

namespace App\Dto\Auth\Output;

final readonly class LoginSuccessOutput
{
    public function __construct(public AuthenticatedUserOutput $user, public string $csrfToken)
    {
    }
}
