<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiView;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users'), IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $r, UserRepository $repo): JsonResponse
    {
        $page = max(1, $r->query->getInt('page', 1));

        return $this->json(['items' => array_map(ApiView::user(...), $repo->findBy([], ['id' => 'DESC'], 30, ($page - 1) * 30)), 'total' => $repo->count([]), 'page' => $page]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $r, UserManager $manager): JsonResponse
    {
        return $this->json(ApiView::user($manager->save(new User(), $r->toArray(), $this->getUser())), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(User $user, Request $r, UserManager $manager): JsonResponse
    {
        return $this->json(ApiView::user($manager->save($user, $r->toArray(), $this->getUser())));
    }
}
