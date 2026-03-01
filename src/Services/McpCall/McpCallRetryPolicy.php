<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use BrainCore\Contracts\McpCall\McpCallResult;

/**
 * MCP Call Retry Policy defines deterministic backoff and max retries.
 */
final class McpCallRetryPolicy
{
    private const DEFAULT_MAX_RETRIES = 3;
    private const BACKOFF_MS = 250;

    public function __construct(
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
    ) {}

    /**
     * Determine if a call result should be retried.
     */
    public function shouldRetry(McpCallResult $result, int $attempts): bool
    {
        if ($result->ok || $attempts > $this->maxRetries) {
            return false;
        }

        $reason = $result->error['reason'] ?? 'unknown';

        return in_array($reason, [
            'transport_timeout',
            'transport_spawn_failed',
            'transport_io_error',
        ], true);
    }

    /**
     * Get the backoff duration in microseconds.
     */
    public function getBackoffMicroseconds(int $attempt): int
    {
        // Simple deterministic backoff: 250ms, 500ms, 750ms...
        return self::BACKOFF_MS * $attempt * 1000;
    }
}
