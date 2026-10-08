<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\LinkWinthorPaymentInput;
use App\Entity\User\User;
use App\Service\Commission\LinkWinthorPaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commissions/{id}/payment/winthor', methods: ['POST'], requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_FINANCE')]
final class LinkWinthorPaymentController extends AbstractController
{
    public function __construct(private readonly LinkWinthorPaymentService $service)
    {
    }

    public function __invoke(
        int $id,
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        LinkWinthorPaymentInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->linkWinthor($id, $input, $actor));
    }
}
