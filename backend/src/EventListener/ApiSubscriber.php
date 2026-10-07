<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Exception\BusinessException;
use App\Service\ExceptionStatus;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ApiSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return ['kernel.request' => [['start', 100], ['guard', 0]], 'kernel.response' => ['response', -10], 'kernel.exception' => ['exception', 5]];
    }

    public function start(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $r = $event->getRequest();

        if (str_starts_with($r->getPathInfo(), '/api') && !$r->isMethodSafe() && '/api/logout' !== $r->getPathInfo()) {
            if (!$this->csrf->isTokenValid(new CsrfToken('api', $r->headers->get('X-CSRF-Token', '')))) {
                throw new BusinessException('Sessão inválida. Atualize a página.', 403);
            }
        }
    }

    public function guard(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }
        $user = $this->security->getUser();

        if ($user instanceof User && !$user->getActive()) {
            throw new BusinessException('Conta desativada.', 401);
        }
        $r = $event->getRequest();

        if ($user instanceof User && $r->hasSession()) {
            $last = $r->getSession()->get('last_activity', time());

            if (time() - $last > 3600) {
                $r->getSession()->invalidate();

                throw new BusinessException('Sessão expirada.', 401);
            }
            $r->getSession()->set('last_activity', time());
        }
    }

    public function response(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $r = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set('X-Request-Id', $r->attributes->get('request_id', ''));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store');
    }

    public function exception(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }
        $e = $event->getThrowable();
        $status = ExceptionStatus::resolve($e, $e instanceof AccessDeniedException && null !== $this->security->getUser());
        $message = match (true) {
            $e instanceof BusinessException => $e->getMessage(), $e instanceof UniqueConstraintViolationException => 'Registro já existente ou lançamento já vinculado.', 401 === $status => 'Faça login para continuar.', 403 === $status => 'Você não tem permissão para esta ação.', 404 === $status => 'Recurso não encontrado.', $status < 500 => 'Requisição inválida.', default => 'Falha interna. Consulte o identificador da requisição nos logs.',
        };
        $id = $event->getRequest()->attributes->get('request_id');

        $event->setResponse(new JsonResponse(['error' => $message, 'requestId' => $id], $status));
    }
}
