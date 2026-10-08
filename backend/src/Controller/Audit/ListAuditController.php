<?php

declare(strict_types=1);

namespace App\Controller\Audit;

use App\Dto\Audit\Input\ListAuditInput;
use App\Service\Audit\ListAuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/audit', methods: ['GET'])]
#[IsGranted('ROLE_AUDITOR')]
final class ListAuditController extends AbstractController
{
    public function __construct(private readonly ListAuditService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListAuditInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
