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

    /** @return array{items: list<Commission>, total: int} */
    public function findPaidPage(string $query, int $page): array
    {
        $builder = $this->createQueryBuilder('commission')->where('commission.status = :status')->setParameter('status', 'paid');

        if ('' !== $query) {
            $builder->andWhere('LOWER(commission.code) LIKE :query OR LOWER(commission.customerName) LIKE :query OR commission.customerCode = :exact OR EXISTS (SELECT matched.id FROM App\Entity\Order\OrderSnapshot matched WHERE matched.commission = commission AND matched.orderNumber = :exact)')
                ->setParameter('query', '%'.mb_strtolower($query).'%')->setParameter('exact', $query);
        }
        $total = (int) (clone $builder)->select('COUNT(commission.id)')->getQuery()->getSingleScalarResult();
        $items = $builder->orderBy('commission.id', 'DESC')->setMaxResults(30)->setFirstResult(($page - 1) * 30)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @param list<int> $orderIds
     * @return list<string>
     */
    public function rejectedCodesForOrders(array $orderIds): array
    {
        if ([] === $orderIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('commission')->select('commission.code')->join('commission.rejectedOrders', 'orders')
            ->where('orders.id IN (:orders)')->setParameter('orders', $orderIds)->getQuery()->getScalarResult();

        return array_values(array_unique(array_column($rows, 'code')));
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

    /** @param list<\App\Entity\Order\OrderSnapshot> $orders
     * @param list<\App\Entity\Adjustment\Adjustment> $adjustments
     */
    public function releaseRejectedAssignments(Commission $commission, array $orders, array $adjustments): void
    {
        // Save the rejection and its archived membership before releasing protected assignments.
        $this->getEntityManager()->flush();

        foreach ($orders as $order) {
            $order->releaseRejectedCommission();
        }

        foreach ($adjustments as $adjustment) {
            $adjustment->setCommission(null);
        }
    }

    public function store(Commission $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }
}
