<?php

declare(strict_types=1);

namespace App\Controller\Audit;

use App\Entity\User\User;
use App\Service\Audit\VerifyCommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/audit/commissions/{id}/verify', methods: ['POST'], requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_AUDITOR')]
final class VerifyCommissionController extends AbstractController
{
    public function __construct(private readonly VerifyCommissionService $service)
    {
    }

    public function __invoke(int $id, #[CurrentUser] User $actor): JsonResponse
    {
        return $this->json($this->service->verify($id, $actor));
    }
}
