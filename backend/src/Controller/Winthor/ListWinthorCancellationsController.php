<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\ListWinthorCancellationsInput;
use App\Service\Winthor\ListWinthorCancellationsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/winthor/cancellations', methods: ['GET'])]
final class ListWinthorCancellationsController extends AbstractController
{
    public function __construct(private readonly ListWinthorCancellationsService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListWinthorCancellationsInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
