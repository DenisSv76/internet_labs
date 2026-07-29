<?php
namespace App\MessageHandler;

use App\Entity\Message;
use App\Message\Notification;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class NotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly MessageRepository $messageRepository,
        private readonly string $myEmail,
    )
    {}

    public function __invoke(Notification $notification): void
    {
        $message = $this->messageRepository->find($notification->contactMessageId);

        if (!$message) {
            throw new \RuntimeException(
                'Contact message not found: ' . $notification->contactMessageId
            );
        }

        $emailOwner = (new Email())
            ->from($this->myEmail)
            ->to($this->myEmail)
            ->subject('Message Notification')
            ->text('У вас есть сообщение: ' . $message->getComment());

        $emailCopy = (new Email())
            ->from($this->myEmail)
            ->to($message->getEmail())
            ->subject('Message Notification')
            ->text('Вы отправили сообщение: ' . $message->getComment());

        $this->mailer->send($emailOwner);
        $this->mailer->send($emailCopy);
        $message->setNotificationSent(true);
        $this->entityManager->flush();
    }
}
