<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\AuditRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class LoginHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrf, private AuditRecorder $audit, private EntityManagerInterface $em)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */ $user = $token->getUser();
        $this->audit->record($user, 'auth.login', 'user:'.$user->getId());
        $this->em->flush();

        return new JsonResponse(['user' => ['id' => $user->getId(), 'name' => $user->getName(), 'email' => $user->getEmail(), 'roles' => $user->getRoles()], 'csrfToken' => $this->csrf->refreshToken('api')->getValue()]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => 'Credenciais inválidas ou conta indisponível.'], 401);
    }
}
