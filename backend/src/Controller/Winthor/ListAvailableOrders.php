<?php

declare(strict_types=1);

namespace App\Controller\Winthor;

use App\Dto\Winthor\Input\SearchOrdersInput;
use App\Exception\BusinessException;
use App\Service\Winthor\SearchAvailableOrders;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/winthor/orders/available', methods: ['GET'])]
#[IsGranted('ROLE_OPERATOR')]
final class ListAvailableOrders extends AbstractController
{
    public function __invoke(Request $request, ValidatorInterface $validator, SearchAvailableOrders $orders): JsonResponse
    {
        $input = new SearchOrdersInput(
            (string) $request->query->get('customer', ''),
            (string) $request->query->get('from', ''),
            (string) $request->query->get('to', ''),
        );
        $violations = $validator->validate($input);

        if (0 < count($violations)) {
            throw new BusinessException((string) $violations[0]->getMessage());
        }

        return $this->json($orders->search($input));
    }
}
