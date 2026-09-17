<?php

namespace App\Tests;

use App\Entity\Message;
use App\Message\Notification;
use App\MessageHandler\NotificationHandler;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NotificationHandlerTest extends TestCase
{
    private const MY_EMAIL = 'owner@example.com';

    public function testSendsTwoEmailsAndMarksMessageAsNotified(): void
    {
        $message = $this->createMock(Message::class);

        $message
            ->expects($this->exactly(2))
            ->method('getComment')
            ->willReturn('Тестовый комментарий');

        $message
            ->expects($this->once())
            ->method('getEmail')
            ->willReturn('user@example.com');

        $message
            ->expects($this->once())
            ->method('setNotificationSent')
            ->with(true);

        $repository = $this->createMock(MessageRepository::class);

        $repository
            ->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($message);

        $mailer = $this->createMock(MailerInterface::class);

        $sentEmails = [];

        $mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with(
                $this->callback(function (Email $email) use (&$sentEmails): bool {
                    $sentEmails[] = $email;

                    return true;
                })
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $handler = new NotificationHandler(
            $entityManager,
            $mailer,
            $repository,
            self::MY_EMAIL,
        );

        $notification = new Notification(123);

        $handler($notification);

        $this->assertCount(2, $sentEmails);
    }

    public function testOwnerEmailContainsCorrectData(): void
    {
        $message = $this->createStub(Message::class);

        $message
            ->method('getComment')
            ->willReturn('Мой тестовый комментарий');

        $message
            ->method('getEmail')
            ->willReturn('user@example.com');

        $repository = $this->createStub(MessageRepository::class);

        $repository
            ->method('find')
            ->willReturn($message);

        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with(
                $this->callback(function (Email $email): bool {
                    static $call = 0;
                    ++$call;

                    if ($call === 1) {
                        return $email->getFrom()[0]->getAddress() === self::MY_EMAIL
                            && $email->getTo()[0]->getAddress() === self::MY_EMAIL
                            && $email->getSubject() === 'Message Notification'
                            && $email->getTextBody() === 'У вас есть сообщение: Мой тестовый комментарий';
                    }

                    return true;
                })
            );

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $handler = new NotificationHandler(
            $entityManager,
            $mailer,
            $repository,
            self::MY_EMAIL,
        );

        $handler(new Notification(123));

        $this->addToAssertionCount(1);
    }

    public function testUserEmailContainsCorrectData(): void
    {
        $message = $this->createStub(Message::class);

        $message
            ->method('getComment')
            ->willReturn('Сообщение пользователя');

        $message
            ->method('getEmail')
            ->willReturn('user@example.com');

        $repository = $this->createStub(MessageRepository::class);

        $repository
            ->method('find')
            ->willReturn($message);

        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with(
                $this->callback(function (Email $email): bool {
                    static $call = 0;
                    ++$call;

                    if ($call === 2) {
                        return $email->getFrom()[0]->getAddress() === self::MY_EMAIL
                            && $email->getTo()[0]->getAddress() === 'user@example.com'
                            && $email->getSubject() === 'Message Notification'
                            && $email->getTextBody() === 'Вы отправили сообщение: Сообщение пользователя';
                    }

                    return true;
                })
            );

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $handler = new NotificationHandler(
            $entityManager,
            $mailer,
            $repository,
            self::MY_EMAIL,
        );

        $handler(new Notification(123));

        $this->addToAssertionCount(1);
    }

    public function testThrowsExceptionWhenMessageDoesNotExist(): void
    {
        $repository = $this->createMock(MessageRepository::class);

        $repository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects($this->never())
            ->method('send');

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager
            ->expects($this->never())
            ->method('flush');

        $handler = new NotificationHandler(
            $entityManager,
            $mailer,
            $repository,
            self::MY_EMAIL,
        );

        try {
            $handler(new Notification(999));

            $this->fail('Ожидалось RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Contact message not found: 999',
                $exception->getMessage()
            );
        }
    }
}
