<?php

declare(strict_types=1);

namespace App\Controller\Report;

use App\Dto\Report\Input\SearchReportInput;
use App\Service\Report\SearchReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports/{kind}', methods: ['GET'], requirements: ['kind' => 'commissions|adjustments'])]
final class SearchReportController extends AbstractController
{
    public function __construct(private readonly SearchReportService $service)
    {
    }

    public function __invoke(
        string $kind,
        #[MapQueryString(validationFailedStatusCode: 422)]
        SearchReportInput $input,
    ): JsonResponse {
        return $this->json($this->service->search($kind, $input));
    }
}
