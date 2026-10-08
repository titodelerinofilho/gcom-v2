<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Service\Commission\GetCommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/commissions/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
final class GetCommissionController extends AbstractController
{
    public function __construct(private readonly GetCommissionService $service)
    {
    }

    public function __invoke(
        int $id,
    ): JsonResponse {
        return $this->json($this->service->get($id));
    }
}
