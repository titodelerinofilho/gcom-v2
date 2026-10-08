<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\ImportReturnInput;
use App\Entity\User\User;
use App\Service\Winthor\ImportReturnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/winthor/returns/import', methods: ['POST'])]
#[IsGranted('ROLE_OPERATOR')]
final class ImportReturnController extends AbstractController
{
    public function __construct(private readonly ImportReturnService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        ImportReturnInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->import($input, $actor), 201);
    }
}
