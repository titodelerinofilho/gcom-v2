<?php

declare(strict_types=1);

namespace App\Security;

class UserSecurity
{
    public static function getUser(): null|array
    {
        return $_SESSION['user'] ?? null;
    }
}