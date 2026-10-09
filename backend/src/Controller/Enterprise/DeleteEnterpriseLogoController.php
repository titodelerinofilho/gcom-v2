<?php

declare(strict_types=1);

namespace App\Controller\Enterprise;

use App\Entity\User\User;
use App\Service\Enterprise\UpdateEnterpriseLogoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings/enterprise/logo', methods: ['DELETE'])]
#[IsGranted('ROLE_ADMIN')]
final class DeleteEnterpriseLogoController extends AbstractController
{
    public function __construct(private readonly UpdateEnterpriseLogoService $service)
    {
    }

    public function __invoke(#[CurrentUser] User $actor): JsonResponse
    {
        return $this->json($this->service->update(null, $actor));
    }
}
