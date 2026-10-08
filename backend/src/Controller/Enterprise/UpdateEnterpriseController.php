<?php

declare(strict_types=1);

namespace App\Controller\Enterprise;

use App\Dto\Enterprise\Input\UpdateEnterpriseInput;
use App\Entity\User\User;
use App\Service\Enterprise\UpdateEnterpriseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings/enterprise', methods: ['PUT'])]
#[IsGranted('ROLE_ADMIN')]
final class UpdateEnterpriseController extends AbstractController
{
    public function __construct(private readonly UpdateEnterpriseService $service)
    {
    }

    public function __invoke(#[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] UpdateEnterpriseInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        return $this->json($this->service->update($input, $actor));
    }
}
