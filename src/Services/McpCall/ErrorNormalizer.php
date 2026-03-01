<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use Throwable;
use BrainCore\Contracts\McpCall\McpCallResult;

/**
 * Error Normalizer maps external/transport errors into the Brain contract.
 */
final class ErrorNormalizer
{
    /**
     * Normalize a Throwable into an McpCallResult error.
     */
    public function normalizeThrowable(Throwable $e, string $serverId, string $tool, ?string $requestId): McpCallResult
    {
        $debug = $this->buildDebugArray($e, $requestId, 'transport_spawn_failed');

        return McpCallResult::error(
            $serverId,
            $tool,
            'MCP_EXECUTION_FAILED',
            'transport_spawn_failed',
            'Failed to spawn MCP server process.',
            'Run: brain mcp:list ; brain mcp:describe --server=<server>',
            $requestId,
            false,
            $debug
        );
    }

    /**
     * Normalize a non-zero exit code into an McpCallResult error.
     */
    public function normalizeExitCode(int $code, string $stderr, string $serverId, string $tool, ?string $requestId): McpCallResult
    {
        // No raw stderr leaks. Map known patterns if needed.
        $message = "MCP server exited with non-zero code ({$code}).";
        $reason = 'transport_io_error';

        if (stripos($stderr, 'timeout') !== false) {
            $reason = 'transport_timeout';
            $message = 'MCP server operation timed out.';
        }

        $debug = $this->buildDebugArray(null, $requestId, $reason, $code, $stderr);

        return McpCallResult::error(
            $serverId,
            $tool,
            'MCP_SERVER_ERROR',
            $reason,
            $message,
            'Run: brain mcp:list ; brain mcp:describe --server=<server>',
            $requestId,
            false,
            $debug
        );
    }

    private function buildDebugArray(?Throwable $e, ?string $requestId, string $normalizedReason, ?int $exitCode = null, ?string $stderr = null): ?array
    {
        $debugMode = getenv('BRAIN_MCP_DEBUG') === '1' || getenv('BRAIN_DEBUG_MCP') === '1';
        if (!$debugMode) {
            return null;
        }

        $verboseMode = getenv('BRAIN_MCP_DEBUG_VERBOSE') === '1';

        $debug = [
            'request_id' => $requestId,
            'normalized_reason' => $normalizedReason,
        ];

        if ($e) {
            $debug['exception_class'] = get_class($e);
            if ($verboseMode) {
                $debug['message'] = McpRedactor::redactString($e->getMessage());
                $debug['stack'] = McpRedactor::redactString($e->getTraceAsString());
            }
        } elseif ($exitCode !== null) {
            $debug['exit_code'] = $exitCode;
            if ($verboseMode && $stderr !== null) {
                $debug['stderr'] = McpRedactor::redactString($stderr);
            }
        }

        return $debug;
    }
}
