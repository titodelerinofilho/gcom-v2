<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\SearchOrdersInput;
use App\Service\Winthor\SearchAvailableOrdersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/winthor/orders/available', methods: ['GET'])]
#[IsGranted('ROLE_OPERATOR')]
final class ListAvailableOrdersController extends AbstractController
{
    public function __construct(private readonly SearchAvailableOrdersService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        SearchOrdersInput $input,
    ): JsonResponse {
        return $this->json($this->service->search($input));
    }
}
