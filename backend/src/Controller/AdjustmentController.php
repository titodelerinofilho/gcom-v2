<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Adjustment;
use App\Exception\BusinessException;
use App\Repository\AdjustmentRepository;
use App\Service\ApiView;
use App\Service\AuditRecorder;
use App\Service\Input;
use App\Service\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/adjustments')]
final class AdjustmentController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $r, AdjustmentRepository $repo): JsonResponse
    {
        $criteria = [];
        $page = max(1, $r->query->getInt('page', 1));

        if ($customer = $r->query->get('customer')) {
            $criteria['customerCode'] = $customer;
        }

        if ($r->query->getBoolean('available')) {
            $criteria['commission'] = null;
        }

        return $this->json(['items' => array_map(ApiView::adjustment(...), $repo->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30)), 'total' => $repo->count($criteria), 'page' => $page]);
    }

    #[Route('', methods: ['POST']), IsGranted('ROLE_OPERATOR')]
    public function create(Request $r, EntityManagerInterface $em, AuditRecorder $audit): JsonResponse
    {
        $data = $r->toArray();
        $type = Input::text($data, 'type', 20);

        if (!in_array($type, ['debt', 'return'], true)) {
            throw new BusinessException('Tipo inválido.');
        }
        $customer = Input::text($data, 'customerCode', 30);

        if (!ctype_digit($customer)) {
            throw new BusinessException('Código do cliente inválido.');
        }
        $a = (new Adjustment())->setCustomerCode($customer)->setType($type)->setAmount(Money::positive(Input::text($data, 'amount', 20)))
            ->setReason(Input::text($data, 'reason', 2000, 10))->setSourceReference(Input::text($data, 'sourceReference', 100))->setCreatedBy($this->getUser());
        $em->wrapInTransaction(function () use ($em, $audit, $a): void {
            $lockSql = 'SELECT pg_advisory_xact_lock(hashtextextended(:source, 0))';
            $em->getConnection()->executeQuery($lockSql, ['source' => 'gcom:customer:'.$a->getCustomerCode()]);
            $em->persist($a);
            $audit->record($this->getUser(), 'adjustment.created', 'customer:'.$a->getCustomerCode(), ['type' => $a->getType(), 'amount' => $a->getAmount(), 'reference' => $a->getSourceReference()]);
        });

        return $this->json(ApiView::adjustment($a), 201);
    }
}
