<?php

declare(strict_types=1);

namespace App\Controller\Reseller;

use App\Dto\Reseller\Input\GetResellerProfileInput;
use App\Service\Reseller\GetResellerProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/resellers/profile', methods: ['GET'])]
final class GetResellerProfileController extends AbstractController
{
    public function __construct(private readonly GetResellerProfileService $service)
    {
    }

    public function __invoke(#[MapQueryString(validationFailedStatusCode: 422)] GetResellerProfileInput $input): JsonResponse
    {
        return $this->json($this->service->get($input));
    }
}
