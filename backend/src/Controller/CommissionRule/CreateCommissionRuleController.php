<?php

declare(strict_types=1);

namespace App\Controller\CommissionRule;

use App\Dto\CommissionRule\Input\CreateCommissionRuleInput;
use App\Entity\User\User;
use App\Service\CommissionRule\CreateCommissionRuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings/commission-calculation', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class CreateCommissionRuleController extends AbstractController
{
    public function __construct(private readonly CreateCommissionRuleService $service)
    {
    }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])]
        CreateCommissionRuleInput $input,
        #[CurrentUser]
        User $actor,
    ): JsonResponse {
        return $this->json($this->service->save($input, $actor), 201);
    }
}
