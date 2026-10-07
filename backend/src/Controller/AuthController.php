<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApiView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AuthController extends AbstractController
{
    #[Route('/api/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/api/csrf', methods: ['GET'])]
    public function csrf(CsrfTokenManagerInterface $manager): JsonResponse
    {
        return $this->json(['csrfToken' => $manager->getToken('api')->getValue()]);
    }

    #[Route('/api/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['error' => 'Autenticação necessária.'], 401);
    }

    #[Route('/api/logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json(['ok' => true]);
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json(ApiView::user($this->getUser()));
    }
}
