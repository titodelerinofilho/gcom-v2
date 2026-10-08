<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\User\Input\UpdateUserInput;
use App\Entity\User\User;
use App\Service\User\UpdateUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_ADMIN')]
final class UpdateUserController extends AbstractController
{
    public function __construct(private readonly UpdateUserService $service)
    {
    }

    public function __invoke(
        int $id,
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        UpdateUserInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->update($id, $input, $actor));
    }
}
