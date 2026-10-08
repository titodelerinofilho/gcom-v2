<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\PreviewCommissionInput;
use App\Entity\User\User;
use App\Service\Commission\PreviewCommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/preview', methods: ['POST'])]
#[IsGranted('ROLE_OPERATOR')]
final class PreviewCommissionController extends AbstractController
{
    public function __construct(private readonly PreviewCommissionService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        PreviewCommissionInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->preview($input, $actor));
    }
}
