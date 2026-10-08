<?php

declare(strict_types=1);

namespace App\Controller\Order;

use App\Dto\Order\Input\ListOrdersInput;
use App\Service\Order\ListOrdersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders', methods: ['GET'])]
final class ListOrdersController extends AbstractController
{
    public function __construct(private readonly ListOrdersService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListOrdersInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
