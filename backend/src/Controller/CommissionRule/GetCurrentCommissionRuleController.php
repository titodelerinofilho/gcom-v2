<?php

declare(strict_types=1);

namespace App\Controller\CommissionRule;

use App\Service\CommissionRule\GetCurrentCommissionRuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings/commission-calculation', methods: ['GET'])]
final class GetCurrentCommissionRuleController extends AbstractController
{
    public function __construct(private readonly GetCurrentCommissionRuleService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->get());
    }
}
