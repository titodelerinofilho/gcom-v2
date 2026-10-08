<?php

declare(strict_types=1);

namespace App\Repository\Commission;

use App\Entity\Commission\Commission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Commission> */
class CommissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commission::class);
    }

    /** @return array{items: list<Commission>, total: int} */
    public function findPage(?string $status, int $page): array
    {
        $criteria = null === $status ? [] : ['status' => $status];

        return ['items' => $this->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30), 'total' => $this->count($criteria)];
    }

    public function locked(int $id): Commission
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE) ?? throw new \App\Exception\Business\BusinessException('Comissão não encontrada.', 404);
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

    public function store(Commission $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }
}
