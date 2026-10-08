<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\User\Input\CreateUserInput;
use App\Entity\User\User;
use App\Service\User\CreateUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class CreateUserController extends AbstractController
{
    public function __construct(private readonly CreateUserService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false], validationGroups: ['Default', 'create'])]
        CreateUserInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->create($input, $actor), 201);
    }
}
