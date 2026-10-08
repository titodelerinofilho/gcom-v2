<?php

declare(strict_types=1);

namespace App\Repository\User;

use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return array{items: list<User>, total: int} */
    public function findPage(int $page): array
    {
        $criteria = [];

        return ['items' => $this->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30), 'total' => $this->count($criteria)];
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $operation
     *
     * @return TResult
     */
    public function save(callable $operation): mixed
    {
        return $this->getEntityManager()->wrapInTransaction($operation);
    }

    public function store(User $entity): void
    {
        $this->getEntityManager()->persist($entity);

        $this->getEntityManager()->flush();
    }
}
