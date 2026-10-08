<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Service\Order\GetOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
final class GetOrderController extends AbstractController
{
    public function __construct(private readonly GetOrderService $service)
    {
    }

    public function __invoke(int $id): JsonResponse
    {
        return $this->json($this->service->get($id));
    }
}
