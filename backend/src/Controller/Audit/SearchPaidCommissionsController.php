<?php

declare(strict_types=1);

namespace App\Controller\Audit;

use App\Dto\Audit\Input\SearchPaidCommissionsInput;
use App\Service\Audit\SearchPaidCommissionsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/audit/commissions', methods: ['GET'])]
#[IsGranted('ROLE_AUDITOR')]
final class SearchPaidCommissionsController extends AbstractController
{
    public function __construct(private readonly SearchPaidCommissionsService $service)
    {
    }

    public function __invoke(#[MapQueryString(validationFailedStatusCode: 422)] SearchPaidCommissionsInput $input): JsonResponse
    {
        return $this->json($this->service->search($input));
    }
}
