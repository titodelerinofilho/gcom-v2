<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\ConfirmPaymentInput;
use App\Entity\User\User;
use App\Service\Commission\ConfirmPaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/{id}/payment', methods: ['POST'], requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_FINANCE')]
final class ConfirmPaymentController extends AbstractController
{
    public function __construct(private readonly ConfirmPaymentService $service)
    {
    }

    public function __invoke(
        int $id,
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        ConfirmPaymentInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->confirm($id, $input, $actor));
    }
}
