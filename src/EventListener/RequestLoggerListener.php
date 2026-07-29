<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Psr\Log\LoggerInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
#[AsEventListener(event: KernelEvents::RESPONSE)]
class RequestLoggerListener
{
    public function __construct(
        private readonly LoggerInterface $requestLogger
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->requestLogger->info('Incoming Request', [
            'method'     => $request->getMethod(),
            'uri'        => $request->getUri(),
            'ip'         => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'content'    => $request->getContent(),
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $this->requestLogger->info('Outgoing Response', [
            'status_code' => $response->getStatusCode(),
            'content'     => $response->getContent(),
        ]);
    }
}
