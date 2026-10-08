<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\Output\UserOutput;
use App\Entity\User\User;

final readonly class GetCurrentUserService
{
    public function get(User $actor): UserOutput
    {
        return new UserOutput($actor);
    }
}
