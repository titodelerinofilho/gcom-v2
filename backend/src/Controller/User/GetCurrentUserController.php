<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\User\User;
use App\Service\User\GetCurrentUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me', methods: ['GET'])]
final class GetCurrentUserController extends AbstractController
{
    public function __construct(private readonly GetCurrentUserService $service)
    {
    }

    public function __invoke(
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->get($actor));
    }
}
