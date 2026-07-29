<?php

namespace App\Controller;

use App\DTO\MessageDto;
use App\Service\MessageService;
use App\Service\RateLimiterService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private readonly RateLimiterService $rateLimiterService,
        private readonly MessageService $messageService,
    )
    {}

    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    #[OA\Post(
        path: '/api/contact',
        summary: 'Отправка формы обратной связи',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', description: 'Имя', type: 'string', example: 'Иван'),
                    new OA\Property(property: 'phone', description: 'Телефон', type: 'string', example: '+79991234567'),
                    new OA\Property(property: 'email', description: 'Email', type: 'string', example: 'ivan@example.com'),
                    new OA\Property(property: 'comment', description: 'Комментарий', type: 'string', example: 'Текст сообщения'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Успешный ответ'),
        ]
    )]
    public function contact(#[MapRequestPayload] MessageDto $messageDto, Request $request,): JsonResponse
    {
        $messageDto->ip = $request->getClientIp();
        $messageDto->agent = $request->headers->get('User-Agent');

        // Исключения (422, 429, 500) обрабатываются глобальным ExceptionListener
        $this->rateLimiterService->consumeOrThrow($messageDto->ip);
        $id = $this->messageService->sendMessage($messageDto);

        return new JsonResponse(['message_id' => $id]);
    }
}
