<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\RejectCommissionInput;
use App\Entity\User\User;
use App\Service\Commission\RejectCommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/{id}/reject', methods: ['POST'], requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_FINANCE')]
final class RejectCommissionController extends AbstractController
{
    public function __construct(private readonly RejectCommissionService $service)
    {
    }

    public function __invoke(int $id, #[MapRequestPayload] RejectCommissionInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        return $this->json($this->service->reject($id, $input, $actor));
    }
}
