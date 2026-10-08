<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Entity\User\User;
use App\Service\Commission\ApproveCommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/{id}/approve', methods: ['POST'], requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_FINANCE')]
final class ApproveCommissionController extends AbstractController
{
    public function __construct(private readonly ApproveCommissionService $service)
    {
    }

    public function __invoke(
        int $id,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->approve($id, $actor));
    }
}
