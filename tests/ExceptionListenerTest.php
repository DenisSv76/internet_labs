<?php

namespace App\Tests;

use App\EventListener\ExceptionListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class ExceptionListenerTest extends TestCase
{
    public function testDoesNothingWhenResponseAlreadyExists(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $httpKernel = $this->createStub(HttpKernelInterface::class);

        $existingResponse = new Response('Already handled', Response::HTTP_BAD_REQUEST);

        $exception = new \RuntimeException('Something went wrong');

        $event = new ExceptionEvent(
            $httpKernel,
            $this->createRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $event->setResponse($existingResponse);

        $logger
            ->expects($this->never())
            ->method('error');

        $listener = new ExceptionListener($logger, $kernel);

        $listener($event);

        $this->assertSame($existingResponse, $event->getResponse());
    }

    public function testHandlesHttpExceptionWith4xxStatus(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $httpKernel = $this->createStub(HttpKernelInterface::class);

        $exception = new BadRequestHttpException('Invalid request');

        $event = new ExceptionEvent(
            $httpKernel,
            $this->createRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $logger
            ->expects($this->never())
            ->method('error');

        $listener = new ExceptionListener($logger, $kernel);

        $listener($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            ['error' => 'Invalid request'],
            json_decode($response->getContent(), true)
        );
        self::assertSame(
            '*',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    public function testHandlesServerErrorInDebugMode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $httpKernel = $this->createStub(HttpKernelInterface::class);

        $kernel
            ->method('isDebug')
            ->willReturn(true);

        $exception = new \RuntimeException('Database connection failed');

        $event = new ExceptionEvent(
            $httpKernel,
            $this->createRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Database connection failed',
                ['exception' => $exception]
            );

        $listener = new ExceptionListener($logger, $kernel);

        $listener($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $response->getStatusCode()
        );

        $body = json_decode($response->getContent(), true);

        self::assertSame(
            'Database connection failed',
            $body['error']
        );
        self::assertSame(
            \RuntimeException::class,
            $body['class']
        );
        self::assertSame(
            $exception->getFile(),
            $body['file']
        );
        self::assertSame(
            $exception->getLine(),
            $body['line']
        );

        self::assertSame(
            '*',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    public function testHandlesServerErrorInProductionMode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $httpKernel = $this->createStub(HttpKernelInterface::class);

        $kernel
            ->method('isDebug')
            ->willReturn(false);

        $exception = new \RuntimeException('Database connection failed');

        $event = new ExceptionEvent(
            $httpKernel,
            $this->createRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Database connection failed',
                ['exception' => $exception]
            );

        $listener = new ExceptionListener($logger, $kernel);

        $listener($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $response->getStatusCode()
        );

        $body = json_decode($response->getContent(), true);

        self::assertSame(
            'Internal server error',
            $body['error']
        );

        self::assertArrayNotHasKey('class', $body);
        self::assertArrayNotHasKey('file', $body);
        self::assertArrayNotHasKey('line', $body);

        self::assertSame(
            '*',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    public function testLogsServerError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $httpKernel = $this->createStub(HttpKernelInterface::class);

        $kernel
            ->method('isDebug')
            ->willReturn(false);

        $exception = new \RuntimeException('Unexpected error');

        $event = new ExceptionEvent(
            $httpKernel,
            $this->createRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected error',
                ['exception' => $exception]
            );

        $listener = new ExceptionListener($logger, $kernel);

        $listener($event);
    }

    public function testCreates500ResponseForGenericThrowable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createStub(KernelInterface::class);
        $httpKernel = $this->createStub(HttpKernelInterface::class);

        $kernel
            ->method('isDebug')
            ->willReturn(false);

        $exception = new \Exception('Something unexpected happened');

        $event = new ExceptionEvent(
            $httpKernel,
            $this->createRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Something unexpected happened',
                ['exception' => $exception]
            );

        $listener = new ExceptionListener($logger, $kernel);

        $listener($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $response->getStatusCode()
        );

        $body = json_decode($response->getContent(), true);

        self::assertSame(
            'Internal server error',
            $body['error']
        );
    }

    private function createRequest(): \Symfony\Component\HttpFoundation\Request
    {
        return \Symfony\Component\HttpFoundation\Request::create('/');
    }
}
