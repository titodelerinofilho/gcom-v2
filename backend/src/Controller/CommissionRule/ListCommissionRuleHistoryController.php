<?php

declare(strict_types=1);

namespace App\Controller\CommissionRule;

use App\Dto\CommissionRule\Input\ListCommissionRuleHistoryInput;
use App\Service\CommissionRule\ListCommissionRuleHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings/commission-calculation/history', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
final class ListCommissionRuleHistoryController extends AbstractController
{
    public function __construct(private readonly ListCommissionRuleHistoryService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListCommissionRuleHistoryInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
