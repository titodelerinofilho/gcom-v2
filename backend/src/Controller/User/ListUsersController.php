<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\User\Input\ListUsersInput;
use App\Service\User\ListUsersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
final class ListUsersController extends AbstractController
{
    public function __construct(private readonly ListUsersService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListUsersInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
