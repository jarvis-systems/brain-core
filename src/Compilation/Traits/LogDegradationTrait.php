<?php

declare(strict_types=1);

namespace BrainCore\Compilation\Traits;

/**
 * Compile-time debug logging for graceful degradation events.
 *
 * Gated by BRAIN_COMPILE_DEBUG env — silent in production.
 * Canonical single mechanism: one trait, one gate, one format.
 *
 * Enterprise invariants:
 * - Compile-time ONLY — never used at runtime / request path.
 * - Side-effect limited to error_log() behind env gate.
 * - MUST NOT log secrets, credentials, or env variable values.
 *   Only context label + exception message are emitted.
 * - Output is single-line: newlines in exception messages are
 *   replaced with spaces for deterministic log parsing.
 * - Hard cap at 500 chars: prevents pathological exception messages
 *   from producing unbounded log output. Truncated messages get
 *   a "[…truncated]" suffix for visibility.
 */
trait LogDegradationTrait
{
    private const MAX_MESSAGE_LENGTH = 500;

    protected static function logDegradation(string $context, \Throwable $e): void
    {
        if (getenv('BRAIN_COMPILE_DEBUG')) {
            $message = str_replace(["\r\n", "\n", "\r"], ' ', $e->getMessage());

            if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
                $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH) . ' [...truncated]';
            }

            error_log("[brain-compile] $context: " . $message);
        }
    }
}
