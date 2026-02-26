<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use BrainCore\Contracts\McpCall\McpCallResult;
use Symfony\Component\Process\Process;

final class StdioAdapter
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ErrorNormalizer $normalizer
    ) {
    }

    /**
     * Resolves and returns the fully formed shell command for dry-run purposes.
     * Applies redaction to the array so absolute paths or secrets are not leaked.
     * 
     * @param non-empty-string[] $command
     * @param array $input The original input to redact
     * @return array<mixed> Normalized result payload containing the redacted command
     */
    public function resolveCommand(array $command, string $serverId, string $tool, array $input = []): array
    {
        [$redactedArgs, $argsRedacted] = McpRedactor::redactArray($command);
        [$redactedInput, $inputRedacted] = McpRedactor::redactArray($input);

        $redactedCommand = McpRedactor::redactString(implode(' ', $redactedArgs));

        return [
            'ok' => true,
            'enabled' => true,
            'kill_switch_env' => 'BRAIN_DISABLE_MCP',
            'server' => $serverId,
            'tool' => $tool,
            'redactions_applied' => $argsRedacted || $inputRedacted,
            'command' => $redactedCommand,
            'args' => $redactedArgs,
            'input' => $redactedInput,
            'transport' => 'stdio',
            'would_execute' => false,
        ];
    }

    /**
     * Executes the given command with the given JSON-RPC payload.
     * Enforces the empty-stderr constraint and returns a normalized result.
     *
     * @param non-empty-string[] $command
     */
    public function execute(
        array $command,
        array $rpcRequest,
        string $serverId,
        string $tool,
        ?string $requestId
    ): McpCallResult {
        $process = new Process($command, $this->projectRoot);

        try {
            $process->setInput(json_encode($rpcRequest, JSON_THROW_ON_ERROR) . "\n");
            $process->run();
        } catch (\Throwable $e) {
            return $this->normalizer->normalizeThrowable($e, $serverId, $tool, $requestId);
        }

        if (!$process->isSuccessful()) {
            return $this->normalizer->normalizeExitCode(
                $process->getExitCode(),
                $process->getErrorOutput(),
                $serverId,
                $tool,
                $requestId
            );
        }

        $output = trim($process->getOutput());
        $lines = explode("\n", $output);
        $rpcResponse = null;

        // MCP servers might output multiple lines, search from the end for a JSON-RPC response
        foreach (array_reverse($lines) as $line) {
            try {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (isset($decoded['jsonrpc']) || isset($decoded['result']) || isset($decoded['error'])) {
                    $rpcResponse = $decoded;
                    break;
                }
            } catch (\JsonException) {
                continue;
            }
        }

        if ($rpcResponse === null) {
            return McpCallResult::error(
                $serverId,
                $tool,
                'MCP_INVALID_RESPONSE',
                'json_rpc_not_found',
                "Server output did not contain a valid JSON-RPC response.",
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        if (isset($rpcResponse['error'])) {
            return McpCallResult::error(
                $serverId,
                $tool,
                'MCP_TOOL_ERROR',
                'server_returned_json_rpc_error',
                'An internal error occurred during tool execution.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        $resultData = $rpcResponse['result'] ?? [];
        $resultData = is_array($resultData) ? $resultData : ['value' => $resultData];
        [$redactedData, $redactionsApplied] = McpRedactor::redactArray($resultData);

        return McpCallResult::success($serverId, $tool, $redactedData, $requestId, $redactionsApplied);
    }
}
