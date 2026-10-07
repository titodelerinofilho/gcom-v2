<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuditEventRepository;
use App\Service\ApiView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AuditController extends AbstractController
{
    #[Route('/api/audit', methods: ['GET']), IsGranted('ROLE_AUDITOR')]
    public function list(Request $r, AuditEventRepository $repo): JsonResponse
    {
        $page = max(1, $r->query->getInt('page', 1));
        $criteria = [];
        foreach (['action', 'subject'] as $key) {
            if ($value = $r->query->get($key)) {
                $criteria[$key] = $value;
            }
        }

        return $this->json(['items' => array_map(ApiView::audit(...), $repo->findBy($criteria, ['id' => 'DESC'], 30, ($page - 1) * 30)), 'total' => $repo->count($criteria), 'page' => $page]);
    }
}
