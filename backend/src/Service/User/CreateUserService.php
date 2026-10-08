<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\Input\CreateUserInput;
use App\Dto\User\Output\UserOutput;
use App\Entity\User\User;

final readonly class CreateUserService
{
    public function __construct(
        private PersistUserService $persistence,
    ) {
    }

    public function create(CreateUserInput $input, ?User $actor): UserOutput
    {
        return new UserOutput($this->persistence->save(new User(), $input, $actor));
    }
}
