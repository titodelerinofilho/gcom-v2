<?php

declare(strict_types=1);

namespace App\Controller\Adjustment;

use App\Dto\Adjustment\Input\CreateAdjustmentInput;
use App\Entity\User\User;
use App\Service\Adjustment\CreateAdjustmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/adjustments', methods: ['POST'])]
#[IsGranted('ROLE_OPERATOR')]
final class CreateAdjustmentController extends AbstractController
{
    public function __construct(private readonly CreateAdjustmentService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        CreateAdjustmentInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->create($input, $actor), 201);
    }
}
