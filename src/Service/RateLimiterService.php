<?php

namespace App\Service;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use DateTimeImmutable;

class RateLimiterService
{
    public function __construct(
        private RateLimiterFactoryInterface $anonymousApiLimiter,
        private int $tokens = 1
    ) {}

    /**
     * Попытаться потребить токены. Вернёт true если принято, иначе бросит TooManyRequestsHttpException.
     *
     * @param string $key ключ лимитера (IP, user id, email, route)
     * @throws TooManyRequestsHttpException
     */
    public function consumeOrThrow(string $key): void
    {
        $limiter = $this->anonymousApiLimiter->create($key);
        $limit = $limiter->consume($this->tokens);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $retrySeconds = $retryAfter instanceof DateTimeImmutable ? $retryAfter->getTimestamp() - time() : 60;
            throw new TooManyRequestsHttpException($retrySeconds, 'Too many requests');
        }
    }

    /**
     * Проверить без исключения, вернуть массив с информацией
     */
    public function check(string $key): array
    {
        $limiter = $this->anonymousApiLimiter->create($key);
        $limit = $limiter->consume(0);

        return [
            'accepted' => $limit->isAccepted(),
            'remaining' => $limit->getRemainingTokens(),
            'retry_after' => $limit->getRetryAfter()?->getTimestamp(),
        ];
    }
}

