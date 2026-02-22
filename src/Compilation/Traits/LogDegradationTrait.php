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
 * - No length cap: error_log() system-level truncation (syslog ~1 KiB)
 *   is sufficient for debug-only VarExporter failure messages.
 */
trait LogDegradationTrait
{
    protected static function logDegradation(string $context, \Throwable $e): void
    {
        if (getenv('BRAIN_COMPILE_DEBUG')) {
            $message = str_replace(["\r\n", "\n", "\r"], ' ', $e->getMessage());
            error_log("[brain-compile] $context: " . $message);
        }
    }
}
