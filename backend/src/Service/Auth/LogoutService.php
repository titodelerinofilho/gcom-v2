<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\Output\LogoutOutput;

final readonly class LogoutService
{
    public function logout(): LogoutOutput
    {
        return new LogoutOutput(true);
    }
}
