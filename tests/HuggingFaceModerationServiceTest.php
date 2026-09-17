<?php

namespace App\Tests;

use App\DTO\ModerateMessageDto;
use App\Service\HuggingFaceModerationService;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceModerationServiceTest extends TestCase
{
    private HttpClientInterface $client;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->client = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    public function testNonToxicMessageIsAllowed(): void
    {
        $platform = $this->createMock(PlatformInterface::class);

        $platform
            ->expects($this->once())
            ->method('invoke')
            ->with(
                'cointegrated/rubert-tiny-toxicity',
                'Привет, как дела?',
                ['task' => Task::TEXT_CLASSIFICATION]
            )
            ->willReturn(
                $this->createDeferredResult([
                    ['label' => 'non-toxic', 'score' => 0.95],
                ])
            );

        $service = $this->createService($platform);

        $result = $service->moderateMessage('Привет, как дела?');

        $this->assertInstanceOf(ModerateMessageDto::class, $result);
        $this->assertTrue($result->isAllowed);
        $this->assertSame(
            'Сообщение допустимо (токсичность: non-toxic, уверенность: 0.95)',
            $result->explanation
        );
    }

    public function testToxicMessageWithScoreAboveThresholdIsNotAllowed(): void
    {
        $platform = $this->createStub(PlatformInterface::class);

        $platform
            ->method('invoke')
            ->willReturn(
                $this->createDeferredResult([
                    ['label' => 'toxic', 'score' => 0.87],
                ])
            );

        $service = $this->createService($platform);

        $result = $service->moderateMessage('Ты идиот');

        $this->assertFalse($result->isAllowed);
        $this->assertSame(
            'Обнаружен недопустимый контент (токсичность: toxic, уверенность: 0.87)',
            $result->explanation
        );
    }

    public function testToxicMessageWithScoreExactlyPointFiveIsAllowed(): void
    {
        $platform = $this->createStub(PlatformInterface::class);

        $platform
            ->method('invoke')
            ->willReturn(
                $this->createDeferredResult([
                    ['label' => 'toxic', 'score' => 0.5],
                ])
            );

        $service = $this->createService($platform);

        $result = $service->moderateMessage('Текст');

        $this->assertTrue($result->isAllowed);
        $this->assertSame(
            'Сообщение допустимо (токсичность: toxic, уверенность: 0.5)',
            $result->explanation
        );
    }

    public function testNonToxicMessageIsAllowedEvenWithHighScore(): void
    {
        $platform = $this->createStub(PlatformInterface::class);

        $platform
            ->method('invoke')
            ->willReturn(
                $this->createDeferredResult([
                    ['label' => 'non-toxic', 'score' => 1.0],
                ])
            );

        $service = $this->createService($platform);

        $result = $service->moderateMessage('Обычный текст');

        $this->assertTrue($result->isAllowed);
        $this->assertSame(
            'Сообщение допустимо (токсичность: non-toxic, уверенность: 1)',
            $result->explanation
        );
    }

    public function testEmptyClassificationsCauseException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);

        $platform
            ->method('invoke')
            ->willReturn(
                $this->createDeferredResult([])
            );

        $service = $this->createService($platform);

        try {
            $service->moderateMessage('Текст');

            $this->fail('Ожидалось исключение');
        } catch (Exception $e) {
            $this->assertSame(
                'Ошибка при обращении к Hugging Face API: Не удалось получить результат от Hugging Face API',
                $e->getMessage()
            );
        }
    }

    public function testPlatformExceptionIsWrapped(): void
    {
        $platform = $this->createMock(PlatformInterface::class);

        $platform
            ->expects($this->once())
            ->method('invoke')
            ->willThrowException(
                new Exception('API недоступен')
            );

        $service = $this->createService($platform);

        try {
            $service->moderateMessage('Текст');

            $this->fail('Ожидалось исключение');
        } catch (Exception $e) {
            $this->assertSame(
                'Ошибка при обращении к Hugging Face API: API недоступен',
                $e->getMessage()
            );
        }
    }

    public function testAllClassificationsAreLogged(): void
    {
        $platform = $this->createStub(PlatformInterface::class);

        $platform
            ->method('invoke')
            ->willReturn(
                $this->createDeferredResult([
                    ['label' => 'toxic', 'score' => 0.87],
                    ['label' => 'non-toxic', 'score' => 0.13],
                ])
            );

        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(
                function (string $message, array $context): void {
                    $this->assertSame('HF classification', $message);
                    $this->assertArrayHasKey('index', $context);
                    $this->assertArrayHasKey('label', $context);
                    $this->assertArrayHasKey('score', $context);
                    $this->assertArrayHasKey('text', $context);
                    $this->assertSame('Текст', $context['text']);
                }
            );

        $service = new HuggingFaceModerationService(
            'test-api-key',
            $this->client,
            $logger,
        );

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('platform');
        $property->setValue($service, $platform);

        $result = $service->moderateMessage('Текст');

        $this->assertFalse($result->isAllowed);
    }

    private function createService(PlatformInterface $platform): HuggingFaceModerationService
    {
        $service = new HuggingFaceModerationService(
            'test-api-key',
            $this->client,
            $this->logger,
        );

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('platform');
        $property->setValue($service, $platform);

        return $service;
    }

    /**
     * @param array<array{label: string, score: float}> $classifications
     */
    private function createDeferredResult(array $classifications): DeferredResult
    {
        $classificationObjects = array_map(
            static fn (array $classification): Classification => new Classification(
                $classification['label'],
                $classification['score'],
            ),
            $classifications
        );

        $objectResult = new ObjectResult(
            new ClassificationResult($classificationObjects)
        );

        $converter = $this->createStub(ResultConverterInterface::class);

        $converter
            ->method('convert')
            ->willReturn($objectResult);

        $rawResult = $this->createStub(RawResultInterface::class);

        return new DeferredResult(
            $converter,
            $rawResult,
        );
    }
}
