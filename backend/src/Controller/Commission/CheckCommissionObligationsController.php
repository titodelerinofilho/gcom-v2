<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\CheckCommissionObligationsInput;
use App\Entity\User\User;
use App\Service\Commission\CheckCommissionObligationsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/checks', methods: ['POST'])]
#[IsGranted('ROLE_OPERATOR')]
final class CheckCommissionObligationsController extends AbstractController
{
    public function __construct(private readonly CheckCommissionObligationsService $service)
    {
    }

    public function __invoke(#[MapRequestPayload] CheckCommissionObligationsInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        return $this->json($this->service->check($input->customerCode, $input->mode, $actor));
    }
}
