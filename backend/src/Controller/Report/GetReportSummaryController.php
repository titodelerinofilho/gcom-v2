<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Dto\Report\Input\GetReportSummaryInput;
use App\Service\Report\GetReportSummaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports/summary', methods: ['GET'])]
final class GetReportSummaryController extends AbstractController
{
    public function __construct(private readonly GetReportSummaryService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        GetReportSummaryInput $input,
    ): JsonResponse {
        return $this->json($this->service->get($input));
    }
}
