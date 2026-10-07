<?php

declare(strict_types=1);

namespace App\Controller\Commission;

use App\Dto\Commission\Input\ConfirmPaymentInput;
use App\Dto\Commission\Output\ConfirmedPaymentOutput;
use App\Exception\BusinessException;
use App\Service\Commission\ConfirmPayment as ConfirmCommissionPayment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/commissions/{id}/payment', requirements: ['id' => '\\d+'], methods: ['POST'])]
#[IsGranted('ROLE_FINANCE')]
final class ConfirmPayment extends AbstractController
{
    public function __invoke(int $id, Request $request, ValidatorInterface $validator, ConfirmCommissionPayment $service): JsonResponse
    {
        $input = ConfirmPaymentInput::fromArray($request->toArray());
        $violations = $validator->validate($input);

        if (0 < count($violations)) {
            throw new BusinessException((string) $violations[0]->getMessage());
        }

        $commission = $service->confirm($id, $input, $this->getUser());

        return $this->json(new ConfirmedPaymentOutput($commission->getId(), $commission->getStatus()));
    }
}
