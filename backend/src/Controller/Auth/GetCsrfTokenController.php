<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\GetCsrfTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/csrf', methods: ['GET'])]
final class GetCsrfTokenController extends AbstractController
{
    public function __construct(private readonly GetCsrfTokenService $service)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->service->get());
    }
}
