<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Service\Report\GetSavedReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports/history/{id}', methods: ['GET'], requirements: ['id' => '[a-f0-9]{32}'])]
final class GetSavedReportController extends AbstractController
{
    public function __construct(private readonly GetSavedReportService $service)
    {
    }

    public function __invoke(string $id): JsonResponse
    {
        return $this->json($this->service->get($id));
    }
}
