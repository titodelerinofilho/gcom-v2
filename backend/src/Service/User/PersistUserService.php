<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\Input\UserWriteInput;
use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Repository\User\UserRepository;
use App\Service\Audit\AuditRecorderService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PersistUserService
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private UserRepository $users,
        private AuditRecorderService $audit,
    ) {
    }

    public function save(User $user, UserWriteInput $input, ?User $actor): User
    {
        $name = $input->name;
        $email = $input->email;
        $roles = array_values(array_unique($input->roles));
        $active = $input->active;

        if (null !== $actor?->getId() && $actor->getId() === $user->getId() && (false === $active || false === in_array('ROLE_ADMIN', $roles, true))) {
            throw new BusinessException('Você não pode remover seu próprio acesso administrativo.');
        }

        $isNew = null === $user->getId();

        if (null !== $input->password) {
            $user->setPassword($this->hasher->hashPassword($user, $input->password));
        }

        return $this->users->save(function () use ($user, $name, $email, $roles, $active, $actor, $isNew) {
            $before = true === $isNew ? null : ['name' => $user->getName(), 'roles' => $user->getRoles(), 'active' => $user->getActive()];

            $user->setName($name)->setEmail($email)->setRoles($roles)->setActive($active);

            $this->users->store($user);

            $this->audit->record($actor, true === $isNew ? 'user.created' : 'user.updated', 'user:'.($user->getId() ?? 'new'), ['before' => $before, 'name' => $name, 'roles' => $roles, 'active' => $active]);

            return $user;
        });
    }
}
