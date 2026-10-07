<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\BusinessException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManager
{
    public const ROLES = ['ROLE_ADMIN', 'ROLE_OPERATOR', 'ROLE_FINANCE', 'ROLE_AUDITOR'];

    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
        private AuditRecorder $audit,
    ) {
    }

    public function save(User $user, array $data, ?User $actor): User
    {
        $name = Input::text($data, 'name', 120, 2);
        $email = mb_strtolower(Input::text($data, 'email', 180));

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException('Email inválido.');
        }

        $roles = $data['roles'] ?? null;

        if (!is_array($roles) || !array_is_list($roles) || !$roles || count($roles) > count(self::ROLES)) {
            throw new BusinessException('Perfis inválidos.');
        }

        foreach ($roles as $role) {
            if (!is_string($role) || !in_array($role, self::ROLES, true)) {
                throw new BusinessException('Perfis inválidos.');
            }
        }

        $roles = array_values(array_unique($roles));
        $active = $data['active'] ?? true;

        if (!is_bool($active)) {
            throw new BusinessException('Status inválido.');
        }

        if ($actor?->getId() && $actor->getId() === $user->getId() && (!$active || !in_array('ROLE_ADMIN', $roles, true))) {
            throw new BusinessException('Você não pode remover seu próprio acesso administrativo.');
        }

        $isNew = null === $user->getId();

        if ($isNew || (array_key_exists('password', $data) && '' !== $data['password'])) {
            $password = $data['password'] ?? null;

            if (!is_string($password) || mb_strlen($password) < 12 || mb_strlen($password) > 128) {
                throw new BusinessException('A senha deve conter de 12 a 128 caracteres.');
            }
            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        return $this->em->wrapInTransaction(function () use ($user, $name, $email, $roles, $active, $actor, $isNew) {
            $before = $isNew ? null : ['name' => $user->getName(), 'roles' => $user->getRoles(), 'active' => $user->getActive()];
            $user->setName($name)->setEmail($email)->setRoles($roles)->setActive($active);

            $this->em->persist($user);
            $this->em->flush();

            $this->audit->record($actor, $isNew ? 'user.created' : 'user.updated', 'user:'.($user->getId() ?? 'new'), ['before' => $before, 'name' => $name, 'roles' => $roles, 'active' => $active]);

            return $user;
        });
    }
}
