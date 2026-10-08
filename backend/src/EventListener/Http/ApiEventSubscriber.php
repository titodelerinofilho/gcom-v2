<?php

declare(strict_types=1);

namespace App\EventListener\Http;

use App\Entity\User\User;
use App\Exception\Business\BusinessException;
use App\Service\Http\ExceptionStatusService;
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
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiEventSubscriber implements EventSubscriberInterface
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
        if (false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (true === str_starts_with($request->getPathInfo(), '/api') && false === $request->isMethodSafe() && '/api/logout' !== $request->getPathInfo()) {
            if (false === $this->csrf->isTokenValid(new CsrfToken('api', $request->headers->get('X-CSRF-Token', '')))) {
                throw new BusinessException('Sessão inválida. Atualize a página.', 403);
            }
        }
    }

    public function guard(RequestEvent $event): void
    {
        if (false === $event->isMainRequest() || false === str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User && false === $user->getActive()) {
            throw new BusinessException('Conta desativada.', 401);
        }

        $request = $event->getRequest();

        if ($user instanceof User && true === $request->hasSession()) {
            $last = $request->getSession()->get('last_activity', time());

            if (time() - $last > 3600) {
                $request->getSession()->invalidate();

                throw new BusinessException('Sessão expirada.', 401);
            }

            $request->getSession()->set('last_activity', time());
        }
    }

    public function response(ResponseEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set('X-Request-Id', $request->attributes->get('request_id', ''));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store');
    }

    public function exception(ExceptionEvent $event): void
    {
        if (false === str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = ExceptionStatusService::resolve($exception, $exception instanceof AccessDeniedException && null !== $this->security->getUser());
        $message = match (true) {
            $exception instanceof BusinessException => $exception->getMessage(), $exception instanceof UniqueConstraintViolationException => 'Registro já existente ou lançamento já vinculado.', 401 === $status => 'Faça login para continuar.', 403 === $status => 'Você não tem permissão para esta ação.', 404 === $status => 'Recurso não encontrado.', $status < 500 => 'Requisição inválida.', default => 'Falha interna. Consulte o identificador da requisição nos logs.',
        };

        if ($exception->getPrevious() instanceof ValidationFailedException) {
            $violations = $exception->getPrevious()->getViolations();
            $message = (string) $violations[0]->getMessage();
        }

        $id = $event->getRequest()->attributes->get('request_id');

        $event->setResponse(new JsonResponse(['error' => $message, 'requestId' => $id], $status));
    }
}
