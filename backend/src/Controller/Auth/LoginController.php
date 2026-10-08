<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\LoginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/login', methods: ['POST'])]
final class LoginController extends AbstractController
{
    public function __construct(private readonly LoginService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->login(), 401);
    }
}
