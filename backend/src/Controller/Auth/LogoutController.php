<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\LogoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/logout', methods: ['POST'])]
final class LogoutController extends AbstractController
{
    public function __construct(private readonly LogoutService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->logout());
    }
}
