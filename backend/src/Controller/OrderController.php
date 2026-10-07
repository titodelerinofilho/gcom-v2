<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrderSnapshot;
use App\Repository\OrderSnapshotRepository;
use App\Service\ApiView;
use App\Service\Input;
use App\Service\OrderImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
final class OrderController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, OrderSnapshotRepository $repo): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $criteria = [];

        if ($request->query->getBoolean('available')) {
            $criteria['commission'] = null;
        }

        if ($customer = $request->query->get('customer')) {
            $criteria['customerCode'] = $customer;
        }

        return $this->json(['items' => array_map(ApiView::order(...), $repo->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30)), 'total' => $repo->count($criteria), 'page' => $page]);
    }

    #[Route('/import', methods: ['POST']), IsGranted('ROLE_OPERATOR')]
    public function import(Request $request, OrderImporter $importer): JsonResponse
    {
        return $this->json(ApiView::order($importer->import(Input::text($request->toArray(), 'orderNumber', 12), $this->getUser()), true), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(OrderSnapshot $order): JsonResponse
    {
        return $this->json(ApiView::order($order, true));
    }
}
