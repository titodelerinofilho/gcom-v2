<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Dto\Order\Input\ImportOrderInput;
use App\Entity\User\User;
use App\Service\Order\ImportOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders/import', methods: ['POST'])]
#[IsGranted('ROLE_OPERATOR')]
final class ImportOrderController extends AbstractController
{
    public function __construct(private readonly ImportOrderService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        ImportOrderInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->import($input, $actor), 201);
    }
}
