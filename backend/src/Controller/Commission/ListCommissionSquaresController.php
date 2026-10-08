<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Service\Commission\CommissionSquareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/squares', methods: ['GET'])]
#[IsGranted('ROLE_OPERATOR')]
final class ListCommissionSquaresController extends AbstractController
{
    public function __construct(private readonly CommissionSquareService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->list());
    }
}
