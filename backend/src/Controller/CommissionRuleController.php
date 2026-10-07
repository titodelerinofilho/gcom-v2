<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CommissionRules;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings/commission-calculation')]
final class CommissionRuleController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function current(CommissionRules $rules): JsonResponse
    {
        return $this->json($rules->current());
    }

    #[Route('/history', methods: ['GET']), IsGranted('ROLE_ADMIN')]
    public function history(Request $request, CommissionRules $rules): JsonResponse
    {
        return $this->json($rules->history(max(1, $request->query->getInt('page', 1))));
    }

    #[Route('', methods: ['POST']), IsGranted('ROLE_ADMIN')]
    public function save(Request $request, CommissionRules $rules): JsonResponse
    {
        return $this->json($rules->save($request->toArray(), $this->getUser()), 201);
    }
}
