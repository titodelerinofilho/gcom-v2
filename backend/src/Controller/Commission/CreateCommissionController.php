<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\CreateCommissionInput;
use App\Entity\User\User;
use App\Service\Commission\CreateCommissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions', methods: ['POST'])]
#[IsGranted('ROLE_OPERATOR')]
final class CreateCommissionController extends AbstractController
{
    public function __construct(private readonly CreateCommissionService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        CreateCommissionInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->create($input, $actor), 201);
    }
}
