<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logs;

use App\EventListener\Logs\ResponseEventListener;
use App\Service\Logs\CorrelationService;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ResponseListenerTest extends TestCase
{
    public function testResponseSizeMeasuresTheSerializedBody(): void
    {
        $request = Request::create('/api/health');
        $requests = new RequestStack();
        $requests->push($request);
        $handler = new TestHandler();
        $logger = new Logger('responses', [$handler]);
        $response = new JsonResponse(['status' => 'ok']);
        $listener = new ResponseEventListener($logger, new CorrelationService($requests), true);
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener($event);

        self::assertSame(strlen($response->getContent()), $handler->getRecords()[0]->context['bytes']);
    }
}
