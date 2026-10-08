<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\Input\UpdateUserInput;
use App\Dto\User\Output\UserOutput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\User\UserRepository;

final readonly class UpdateUserService
{
    public function __construct(
        private PersistUserService $persistence,
        private UserRepository $repository,
    ) {
    }

    public function update(int $id, UpdateUserInput $input, User $actor): UserOutput
    {
        $user = $this->repository->find($id) ?? throw new BusinessException('Usuário não encontrado.', 404);

        return new UserOutput($this->persistence->save($user, $input, $actor));
    }
}
