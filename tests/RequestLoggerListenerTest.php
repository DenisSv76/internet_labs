<?php

namespace App\Tests;

use App\EventListener\RequestLoggerListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestLoggerListenerTest extends TestCase
{
    public function testOnKernelRequestLogsMainRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $request = Request::create(
            '/api/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PHPUnit Test',
            ],
            '{"name":"John","email":"john@example.com"}'
        );

        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Incoming Request',
                [
                    'method' => 'POST',
                    'uri' => $request->getUri(),
                    'ip' => '127.0.0.1',
                    'user_agent' => 'PHPUnit Test',
                    'content' => '{"name":"John","email":"john@example.com"}',
                ]
            );

        $listener = new RequestLoggerListener($logger);

        $listener->onKernelRequest($event);
    }

    public function testOnKernelRequestDoesNotLogSubRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $request = Request::create('/api/contact');

        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST
        );

        $logger
            ->expects($this->never())
            ->method('info');

        $listener = new RequestLoggerListener($logger);

        $listener->onKernelRequest($event);
    }

    public function testOnKernelResponseLogsMainResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $request = Request::create('/api/contact');

        $response = new Response(
            '{"success":true}',
            Response::HTTP_CREATED
        );

        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Outgoing Response',
                [
                    'status_code' => Response::HTTP_CREATED,
                    'content' => '{"success":true}',
                ]
            );

        $listener = new RequestLoggerListener($logger);

        $listener->onKernelResponse($event);
    }

    public function testOnKernelResponseDoesNotLogSubRequest(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $request = Request::create('/api/contact');

        $response = new Response(
            '{"success":true}',
            Response::HTTP_OK
        );

        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        $logger
            ->expects($this->never())
            ->method('info');

        $listener = new RequestLoggerListener($logger);

        $listener->onKernelResponse($event);
    }
}
