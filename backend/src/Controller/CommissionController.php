<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commission;
use App\Exception\BusinessException;
use App\Repository\AdjustmentRepository;
use App\Repository\CommissionRepository;
use App\Repository\PaymentLinkRepository;
use App\Service\ApiView;
use App\Service\CommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions')]
final class CommissionController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, CommissionRepository $repo): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $criteria = [];

        if ($status = $request->query->get('status')) {
            if (!in_array($status, ['pending', 'approved', 'paid'], true)) {
                throw new BusinessException('Status inválido.');
            }
            $criteria['status'] = $status;
        }

        return $this->json(['items' => array_map(ApiView::commission(...), $repo->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30)), 'total' => $repo->count($criteria), 'page' => $page]);
    }

    #[Route('', methods: ['POST']), IsGranted('ROLE_OPERATOR')]
    public function create(Request $r, CommissionService $service): JsonResponse
    {
        return $this->json(ApiView::commission($service->create($r->toArray(), $this->getUser()), true), 201);
    }

    #[Route('/preview', methods: ['POST']), IsGranted('ROLE_OPERATOR')]
    public function preview(Request $request, CommissionService $service): JsonResponse
    {
        return $this->json($service->preview($request->toArray(), $this->getUser()));
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(Commission $commission, PaymentLinkRepository $payments, AdjustmentRepository $adjustments): JsonResponse
    {
        $data = ApiView::commission($commission, true);
        $payment = $payments->findOneBy(['commission' => $commission]);
        $data['payment'] = $payment ? ApiView::payment($payment) : null;
        $data['adjustments'] = array_map(ApiView::adjustment(...), $adjustments->findBy(['commission' => $commission]));

        return $this->json($data);
    }

    #[Route('/{id}/approve', requirements: ['id' => '\d+'], methods: ['POST']), IsGranted('ROLE_FINANCE')]
    public function approve(int $id, CommissionService $service): JsonResponse
    {
        return $this->json(ApiView::commission($service->approve($id, $this->getUser())));
    }

    #[Route('/{id}/payment/winthor', requirements: ['id' => '\\d+'], methods: ['POST']), IsGranted('ROLE_FINANCE')]
    public function linkWinthor(int $id, Request $r, CommissionService $service): JsonResponse
    {
        return $this->json(ApiView::commission($service->linkWinthor($id, $r->toArray(), $this->getUser())));
    }
}
