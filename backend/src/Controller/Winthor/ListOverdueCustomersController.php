<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\ListOverdueCustomersInput;
use App\Service\Winthor\ListOverdueCustomersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/winthor/overdue', methods: ['GET'])]
final class ListOverdueCustomersController extends AbstractController
{
    public function __construct(private readonly ListOverdueCustomersService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListOverdueCustomersInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
