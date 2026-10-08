<?php

declare(strict_types=1);

namespace App\Controller\Enterprise;

use App\Service\Enterprise\GetEnterpriseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings/enterprise', methods: ['GET'])]

final class GetEnterpriseController extends AbstractController
{
    public function __construct(private readonly GetEnterpriseService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->get());
    }
}
