<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\Input\ListUsersInput;
use App\Dto\User\Output\ListUsersOutput;
use App\Dto\User\Output\UserOutput;
use App\Entity\User\User;
use App\Repository\User\UserRepository;

final readonly class ListUsersService
{
    public function __construct(
        private UserRepository $repository,
    ) {
    }

    public function list(ListUsersInput $input): ListUsersOutput
    {
        $page = $this->repository->findPage($input->page);
        $items = array_map(static fn (User $item): UserOutput => new UserOutput($item), $page['items']);

        return new ListUsersOutput($items, $page['total'], $input->page);
    }
}
