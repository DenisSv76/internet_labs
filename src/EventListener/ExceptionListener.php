<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -128)]
class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if ($event->getResponse() !== null) {
            return;
        }

        $throwable = $event->getThrowable();

        $statusCode = $throwable instanceof HttpExceptionInterface
            ? $throwable->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $isDebug = $this->kernel->isDebug();

        if ($statusCode >= 500) {
            $this->logger->error($throwable->getMessage(), [
                'exception' => $throwable,
            ]);

            $body = [
                'error' => $isDebug
                    ? $throwable->getMessage()
                    : 'Internal server error',
            ];

            if ($isDebug) {
                $body['class'] = $throwable::class;
                $body['file'] = $throwable->getFile();
                $body['line'] = $throwable->getLine();
            }
        } else {
            $body = [
                'error' => $throwable->getMessage(),
            ];
        }

        $response = new JsonResponse($body, $statusCode);
        $response->headers->set('Access-Control-Allow-Origin', '*');

        $event->setResponse($response);
    }
}
