<?php
namespace App\Service;

use App\DTO\MessageDto;
use App\Entity\Message;
use App\Message\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MessageService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly HuggingFaceModerationService $moderationService,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * @throws Exception
     * @throws UnprocessableEntityHttpException
     */
    public function sendMessage(MessageDto $messageDto): ?int
    {
        $errors = $this->validator->validate($messageDto);

        if (count($errors) > 0) {
            $errorsString = (string)$errors;
            throw new UnprocessableEntityHttpException('There are errors ' . $errorsString . ' when sending a message');
        }
        $moderateMessage = $this->moderationService->moderateMessage($messageDto->comment);

        if ($moderateMessage->isAllowed === false) {
            throw new UnprocessableEntityHttpException("Ваше сообщение неприемлемо по следующей причине: {$moderateMessage->explanation}");
        }

        $message = new Message();
        $message->setName($messageDto->name);
        $message->setPhone($messageDto->phone);
        $message->setEmail($messageDto->email);
        $message->setComment($messageDto->comment);
        $message->setIp($messageDto->ip);
        $message->setUserAgent($messageDto->agent);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $notification = new Notification(
            contactMessageId: $message->getId(),
        );
        $this->bus->dispatch($notification);

        return $message->getId();
    }
}
