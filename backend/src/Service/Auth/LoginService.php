<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\Output\AuthenticatedUserOutput;
use App\Dto\Auth\Output\LoginOutput;
use App\Dto\Auth\Output\LoginSuccessOutput;
use App\Entity\User\User;
use App\Repository\Audit\AuditEventRepository;
use App\Service\Audit\AuditRecorderService;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class LoginService
{
    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private AuditRecorderService $audit,
        private AuditEventRepository $auditEvents,
    ) {
    }

    public function login(): LoginOutput
    {
        return new LoginOutput('Autenticação necessária.');
    }

    public function authenticated(User $user): LoginSuccessOutput
    {
        $this->audit->record($user, 'auth.login', 'user:'.$user->getId());
        $this->auditEvents->savePending();

        return new LoginSuccessOutput(new AuthenticatedUserOutput($user), $this->csrf->refreshToken('api')->getValue());
    }
}
