<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\ListCommissionsInput;
use App\Service\Commission\ListCommissionsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/commissions', methods: ['GET'])]
final class ListCommissionsController extends AbstractController
{
    public function __construct(private readonly ListCommissionsService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListCommissionsInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
