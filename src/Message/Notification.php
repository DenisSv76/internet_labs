<?php
namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;
#[AsMessage('async')]
class Notification
{
    public function __construct(
        public int $contactMessageId,
    ) {}
}
