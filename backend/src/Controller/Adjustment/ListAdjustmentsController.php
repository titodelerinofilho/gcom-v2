<?php

declare(strict_types=1);

namespace App\Controller\Adjustment;

use App\Dto\Adjustment\Input\ListAdjustmentsInput;
use App\Service\Adjustment\ListAdjustmentsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/adjustments', methods: ['GET'])]
final class ListAdjustmentsController extends AbstractController
{
    public function __construct(private readonly ListAdjustmentsService $service)
    {
    }

    public function __invoke(#[MapQueryString(validationFailedStatusCode: 422)] ListAdjustmentsInput $input): JsonResponse
    {
        return $this->json($this->service->list($input));
    }
}
