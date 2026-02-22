<?php

declare(strict_types=1);

namespace BrainCore\Compilation\Traits;

/**
 * Compile-time debug logging for graceful degradation events.
 *
 * Gated by BRAIN_COMPILE_DEBUG env — silent in production.
 * Canonical single mechanism: one trait, one gate, one format.
 */
trait LogDegradationTrait
{
    protected static function logDegradation(string $context, \Throwable $e): void
    {
        if (getenv('BRAIN_COMPILE_DEBUG')) {
            error_log("[brain-compile] $context: " . $e->getMessage());
        }
    }
}
