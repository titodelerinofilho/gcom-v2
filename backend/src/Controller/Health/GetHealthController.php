<?php

declare(strict_types=1);

namespace App\Controller\Health;

use App\Service\Health\GetHealthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health', methods: ['GET'])]
final class GetHealthController extends AbstractController
{
    public function __construct(private readonly GetHealthService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->get());
    }
}
