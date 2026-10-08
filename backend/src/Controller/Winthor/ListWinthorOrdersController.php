<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\ListWinthorOrdersInput;
use App\Service\Winthor\ListWinthorOrdersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/winthor/orders', methods: ['GET'])]
final class ListWinthorOrdersController extends AbstractController
{
    public function __construct(private readonly ListWinthorOrdersService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListWinthorOrdersInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
