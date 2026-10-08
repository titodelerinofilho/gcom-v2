<?php

declare(strict_types=1);

namespace App\Service\Logs;

use Symfony\Component\HttpFoundation\RequestStack;

final class CorrelationService
{
    private ?string $cliId = null;

    public function __construct(private RequestStack $requests)
    {
    }

    public function getCorrelationIdentification(): string
    {
        $request = $this->requests->getMainRequest();

        if (null === $request) {
            return $this->cliId ??= 'cli-'.bin2hex(random_bytes(16));
        }

        if (false === $request->attributes->has('request_id')) {
            $request->attributes->set('request_id', bin2hex(random_bytes(16)));
            $request->attributes->set('started_at', microtime(true));
        }

        return $request->attributes->get('request_id');
    }
}
