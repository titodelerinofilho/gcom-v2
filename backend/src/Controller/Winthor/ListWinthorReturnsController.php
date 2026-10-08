<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\ListWinthorReturnsInput;
use App\Service\Winthor\ListWinthorReturnsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/winthor/returns', methods: ['GET'])]
final class ListWinthorReturnsController extends AbstractController
{
    public function __construct(private readonly ListWinthorReturnsService $service)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ListWinthorReturnsInput $input,
    ): JsonResponse {
        return $this->json($this->service->list($input));
    }
}
