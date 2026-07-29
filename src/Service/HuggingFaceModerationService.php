<?php

namespace App\Service;

use App\DTO\ModerateMessageDto;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceModerationService
{
    private $platform;

    public function __construct(
        private readonly string $huggingFaceApiKey,
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
        $this->platform = Factory::createPlatform(
            apiKey: $this->huggingFaceApiKey,
            httpClient: $this->client,
        );
    }

    /**
     * Проверяет текст на токсичность с помощью модели cointegrated/rubert-tiny-toxicity.
     *
     * @throws Exception
     */
    public function moderateMessage(string $question): ModerateMessageDto
    {
        try {
            $model = 'cointegrated/rubert-tiny-toxicity';

            $result = $this->platform->invoke(
                $model,
                $question,
                ['task' => Task::TEXT_CLASSIFICATION]
            );

            $classifications = $result->asObject()->getClassifications();

            if (empty($classifications)) {
                throw new Exception('Не удалось получить результат от Hugging Face API');
            }

            foreach ($classifications as $i => $c) {
                $this->logger->debug('HF classification', [
                    'index' => $i,
                    'label' => $c->getLabel(),
                    'score' => $c->getScore(),
                    'text'  => $question,
                ]);
            }

            $topResult = $classifications[0];
            $label = $topResult->getLabel();
            $score = $topResult->getScore();
            $isToxic = ($label !== 'non-toxic' && $score > 0.5);

            $isAllowed = !$isToxic;

            $explanation = $isAllowed
                ? "Сообщение допустимо (токсичность: {$label}, уверенность: " . round($score, 2) . ")"
                : "Обнаружен недопустимый контент (токсичность: {$label}, уверенность: " . round($score, 2) . ")";

            return new ModerateMessageDto($isAllowed, $explanation);

        } catch (\Throwable $e) {
            throw new Exception('Ошибка при обращении к Hugging Face API: ' . $e->getMessage());
        }
    }
}
