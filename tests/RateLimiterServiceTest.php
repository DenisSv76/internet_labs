<?php

namespace App\Tests;

use App\Service\RateLimiterService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RateLimiterServiceTest extends TestCase
{
    public function testConsumeOrThrowDoesNotThrowWhenLimitIsAccepted(): void
    {
        $limit = $this->createStub(RateLimit::class);

        $limit
            ->method('isAccepted')
            ->willReturn(true);

        $limiter = $this->createMock(LimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($limit);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);

        $factory
            ->expects($this->once())
            ->method('create')
            ->with('test-key')
            ->willReturn($limiter);

        $service = new RateLimiterService($factory);

        $service->consumeOrThrow('test-key');

        $this->addToAssertionCount(1);
    }

    public function testConsumeOrThrowThrowsWhenLimitIsNotAccepted(): void
    {
        $retryAfter = new DateTimeImmutable('+30 seconds');

        $limit = $this->createStub(RateLimit::class);

        $limit
            ->method('isAccepted')
            ->willReturn(false);

        $limit
            ->method('getRetryAfter')
            ->willReturn($retryAfter);

        $limiter = $this->createStub(LimiterInterface::class);

        $limiter
            ->method('consume')
            ->willReturn($limit);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);

        $factory
            ->method('create')
            ->willReturn($limiter);

        $service = new RateLimiterService($factory);

        try {
            $service->consumeOrThrow('test-key');

            $this->fail('Ожидалось TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $exception) {
            $this->assertSame(
                'Too many requests',
                $exception->getMessage()
            );
        }
    }

    public function testConsumeOrThrowSetsRetryAfterHeader(): void
    {
        $retryAfter = new DateTimeImmutable('+30 seconds');

        $limit = $this->createStub(RateLimit::class);

        $limit
            ->method('isAccepted')
            ->willReturn(false);

        $limit
            ->method('getRetryAfter')
            ->willReturn($retryAfter);

        $limiter = $this->createStub(LimiterInterface::class);

        $limiter
            ->method('consume')
            ->willReturn($limit);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);

        $factory
            ->method('create')
            ->willReturn($limiter);

        $service = new RateLimiterService($factory);

        try {
            $service->consumeOrThrow('test-key');

            $this->fail('Ожидалось TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $exception) {
            $retryAfterHeader = $exception->getHeaders()['Retry-After'] ?? null;

            $this->assertNotNull($retryAfterHeader);

            $retrySeconds = (int) $retryAfterHeader;

            $this->assertGreaterThanOrEqual(29, $retrySeconds);
            $this->assertLessThanOrEqual(31, $retrySeconds);
        }
    }

    public function testCheckReturnsAcceptedAndRemainingTokens(): void
    {
        $retryAfter = new DateTimeImmutable('+60 seconds');

        $limit = $this->createStub(RateLimit::class);

        $limit
            ->method('isAccepted')
            ->willReturn(true);

        $limit
            ->method('getRemainingTokens')
            ->willReturn(5);

        $limit
            ->method('getRetryAfter')
            ->willReturn($retryAfter);

        $limiter = $this->createMock(LimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('consume')
            ->with(0)
            ->willReturn($limit);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);

        $factory
            ->expects($this->once())
            ->method('create')
            ->with('test-key')
            ->willReturn($limiter);

        $service = new RateLimiterService($factory);

        $result = $service->check('test-key');

        $this->assertSame(
            [
                'accepted' => true,
                'remaining' => 5,
                'retry_after' => $retryAfter->getTimestamp(),
            ],
            $result
        );
    }

    public function testCheckReturnsRejectedStatusAndRemainingTokens(): void
    {
        $retryAfter = new DateTimeImmutable('+30 seconds');

        $limit = $this->createStub(RateLimit::class);

        $limit
            ->method('isAccepted')
            ->willReturn(false);

        $limit
            ->method('getRemainingTokens')
            ->willReturn(0);

        $limit
            ->method('getRetryAfter')
            ->willReturn($retryAfter);

        $limiter = $this->createStub(LimiterInterface::class);

        $limiter
            ->method('consume')
            ->willReturn($limit);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);

        $factory
            ->method('create')
            ->willReturn($limiter);

        $service = new RateLimiterService($factory);

        $result = $service->check('test-key');

        $this->assertSame(
            [
                'accepted' => false,
                'remaining' => 0,
                'retry_after' => $retryAfter->getTimestamp(),
            ],
            $result
        );
    }

    public function testCustomTokenAmountIsPassedToLimiter(): void
    {
        $limit = $this->createStub(RateLimit::class);

        $limit
            ->method('isAccepted')
            ->willReturn(true);

        $limiter = $this->createMock(LimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('consume')
            ->with(5)
            ->willReturn($limit);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);

        $factory
            ->expects($this->once())
            ->method('create')
            ->with('test-key')
            ->willReturn($limiter);

        $service = new RateLimiterService($factory, 5);

        $service->consumeOrThrow('test-key');

        $this->addToAssertionCount(1);
    }
}
