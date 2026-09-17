<?php

namespace App\Tests;

use App\Controller\ContactController;
use App\DTO\MessageDto;
use App\Service\MessageService;
use App\Service\RateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class ContactControllerTest extends TestCase
{
    public function testContactSendsMessageAndReturnsMessageId(): void
    {
        $rateLimiterService = $this->createMock(RateLimiterService::class);
        $messageService = $this->createMock(MessageService::class);

        $rateLimiterService
            ->expects($this->once())
            ->method('consumeOrThrow')
            ->with('192.168.1.100');

        $messageService
            ->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(
                function (MessageDto $messageDto): bool {
                    return $messageDto->name === 'Иван'
                        && $messageDto->email === 'ivan@example.com'
                        && $messageDto->phone === '+79991234567'
                        && $messageDto->comment === 'Тестовое сообщение'
                        && $messageDto->ip === '192.168.1.100'
                        && $messageDto->agent === 'Test Browser/1.0';
                }
            ))
            ->willReturn(123);

        $controller = new ContactController(
            $rateLimiterService,
            $messageService,
        );

        $messageDto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            'Тестовое сообщение',
        );

        $request = Request::create(
            '/api/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '192.168.1.100',
            ]
        );

        $request->headers->set('User-Agent', 'Test Browser/1.0');

        $response = $controller->contact($messageDto, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(
            ['message_id' => 123],
            json_decode($response->getContent(), true)
        );

        $this->assertSame('192.168.1.100', $messageDto->ip);
        $this->assertSame('Test Browser/1.0', $messageDto->agent);
    }

    public function testContactPassesClientIpToRateLimiter(): void
    {
        $rateLimiterService = $this->createMock(RateLimiterService::class);
        $messageService = $this->createStub(MessageService::class);

        $rateLimiterService
            ->expects($this->once())
            ->method('consumeOrThrow')
            ->with('10.20.30.40');

        $messageService
            ->method('sendMessage')
            ->willReturn(1);

        $controller = new ContactController(
            $rateLimiterService,
            $messageService,
        );

        $messageDto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            'Тест',
        );

        $request = Request::create(
            '/api/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '10.20.30.40',
            ]
        );

        $controller->contact($messageDto, $request);

        $this->assertSame('10.20.30.40', $messageDto->ip);
    }

    public function testContactStoresUserAgentInDto(): void
    {
        $rateLimiterService = $this->createStub(RateLimiterService::class);

        $rateLimiterService
            ->method('consumeOrThrow');

        $messageService = $this->createMock(MessageService::class);

        $messageService
            ->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(
                function (MessageDto $messageDto): bool {
                    return $messageDto->agent === 'Mozilla/5.0 Test';
                }
            ))
            ->willReturn(456);

        $controller = new ContactController(
            $rateLimiterService,
            $messageService,
        );

        $messageDto = new MessageDto(
            'Петр',
            'petr@example.com',
            '+79990001122',
            'Сообщение',
        );

        $request = Request::create(
            '/api/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );

        $request->headers->set('User-Agent', 'Mozilla/5.0 Test');

        $controller->contact($messageDto, $request);

        $this->assertSame('Mozilla/5.0 Test', $messageDto->agent);
    }

    public function testContactDoesNotSendMessageWhenRateLimitThrowsException(): void
    {
        $rateLimiterService = $this->createMock(RateLimiterService::class);
        $messageService = $this->createMock(MessageService::class);

        $rateLimiterService
            ->expects($this->once())
            ->method('consumeOrThrow')
            ->with('192.168.1.50')
            ->willThrowException(
                new \RuntimeException('Too many requests')
            );

        $messageService
            ->expects($this->never())
            ->method('sendMessage');

        $controller = new ContactController(
            $rateLimiterService,
            $messageService,
        );

        $messageDto = new MessageDto(
            'Иван',
            'ivan@example.com',
            '+79991234567',
            'Тест',
        );

        $request = Request::create(
            '/api/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '192.168.1.50',
            ]
        );

        $this->expectException(\RuntimeException::class);

        $controller->contact($messageDto, $request);
    }

    public function testContactReturnsMessageIdFromMessageService(): void
    {
        $rateLimiterService = $this->createStub(RateLimiterService::class);

        $rateLimiterService
            ->method('consumeOrThrow');

        $messageService = $this->createMock(MessageService::class);

        $messageService
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturn(98765);

        $controller = new ContactController(
            $rateLimiterService,
            $messageService,
        );

        $messageDto = new MessageDto(
            'Анна',
            'anna@example.com',
            '+79995556677',
            'Проверка ID',
        );

        $request = Request::create(
            '/api/contact',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '172.16.0.10',
            ]
        );

        $response = $controller->contact($messageDto, $request);

        $this->assertSame(
            ['message_id' => 98765],
            json_decode($response->getContent(), true)
        );
    }
}
