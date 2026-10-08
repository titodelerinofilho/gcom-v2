<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\Output\CsrfTokenOutput;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class GetCsrfTokenService
{
    public function __construct(
        private CsrfTokenManagerInterface $manager,
    ) {
    }

    public function get(): CsrfTokenOutput
    {
        return new CsrfTokenOutput($this->manager->getToken('api')->getValue());
    }
}
