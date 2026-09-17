<?php

namespace App\Tests;

use App\DTO\MessageDto;
use App\DTO\ModerateMessageDto;
use App\Entity\Message;
use App\Message\Notification;
use App\Service\HuggingFaceModerationService;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MessageServiceTest extends TestCase
{
    public function testSendMessagePersistsAndDispatches(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $moderationService = $this->createMock(HuggingFaceModerationService::class);
        $bus = $this->createMock(MessageBusInterface::class);

        $messageDto = new MessageDto(
            name: 'John Doe',
            email: 'john@example.com',
            phone: '+123456789',
            comment: 'Hello, this is a valid message.',
            ip: '127.0.0.1',
            agent: 'PHPUnit',
        );

        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($messageDto)
            ->willReturn(new ConstraintViolationList());

        $moderationService
            ->expects($this->once())
            ->method('moderateMessage')
            ->with($messageDto->comment)
            ->willReturn(new ModerateMessageDto(
                isAllowed: true,
                explanation: 'Message is allowed.',
            ));

        $message = null;

        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(
                function (Message $persistedMessage) use (&$message): bool {
                    $message = $persistedMessage;

                    return $persistedMessage->getName() === 'John Doe'
                        && $persistedMessage->getEmail() === 'john@example.com'
                        && $persistedMessage->getPhone() === '+123456789'
                        && $persistedMessage->getComment() === 'Hello, this is a valid message.'
                        && $persistedMessage->getIp() === '127.0.0.1'
                        && $persistedMessage->getUserAgent() === 'PHPUnit';
                }
            ));

        $entityManager
            ->expects($this->once())
            ->method('flush')
            ->willReturnCallback(
                function () use (&$message): void {
                    $reflection = new \ReflectionProperty(Message::class, 'id');
                    $reflection->setValue($message, 123);
                }
            );

        $bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                function (Notification $notification): bool {
                    return $notification->contactMessageId === 123;
                }
            ))
            ->willReturn(new Envelope(new Notification(123)));

        $service = new MessageService(
            entityManager: $entityManager,
            validator: $validator,
            moderationService: $moderationService,
            bus: $bus,
        );

        $result = $service->sendMessage($messageDto);

        $this->assertSame(123, $result);
    }

    public function testSendMessageThrowsOnValidationErrors(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $moderationService = $this->createMock(HuggingFaceModerationService::class);
        $bus = $this->createMock(MessageBusInterface::class);

        $messageDto = new MessageDto(
            name: '',
            email: 'invalid-email',
            phone: '',
            comment: '',
            ip: '127.0.0.1',
            agent: 'PHPUnit',
        );

        $violations = new ConstraintViolationList();

        $violation = $this->createStub(ConstraintViolationInterface::class);

        $violations->add($violation);

        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($messageDto)
            ->willReturn($violations);

        $moderationService
            ->expects($this->never())
            ->method('moderateMessage');

        $entityManager
            ->expects($this->never())
            ->method('persist');

        $entityManager
            ->expects($this->never())
            ->method('flush');

        $bus
            ->expects($this->never())
            ->method('dispatch');

        $service = new MessageService(
            entityManager: $entityManager,
            validator: $validator,
            moderationService: $moderationService,
            bus: $bus,
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        $service->sendMessage($messageDto);
    }
}
