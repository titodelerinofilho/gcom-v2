<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Dto\Report\Input\ListSavedReportsInput;
use App\Service\Report\ListSavedReportsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports/history', methods: ['GET'])]
final class ListSavedReportsController extends AbstractController
{
    public function __construct(private readonly ListSavedReportsService $service)
    {
    }

    public function __invoke(#[MapQueryString(validationFailedStatusCode: 422)] ListSavedReportsInput $input): JsonResponse
    {
        return $this->json($this->service->list($input));
    }
}
