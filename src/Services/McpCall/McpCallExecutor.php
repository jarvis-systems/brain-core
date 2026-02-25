<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use BrainCore\Contracts\McpCall\McpCallRequest;
use BrainCore\Contracts\McpCall\McpCallResult;
use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpExternalToolsPolicy\McpExternalToolsPolicyResolver;
use BrainCore\Mcp\StdioMcp;
use Symfony\Component\Process\Process;
use RuntimeException;

/**
 * MCP Call Executor handles the actual execution of MCP server tools via stdio.
 */
final class McpCallExecutor
{
    public function __construct(
        private readonly McpRegistryResolver $registryResolver,
        private readonly McpExternalToolsPolicyResolver $policyResolver,
        private readonly string $projectRoot,
    ) {}

    /**
     * Execute an MCP call.
     */
    public function execute(McpCallRequest $request, bool $trace = false): McpCallResult
    {
        $requestId = null;
        if ($trace) {
            $stableInput = json_encode($request->input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $requestId = substr(hash('sha256', $request->serverId . '|' . $request->tool . '|' . $stableInput), 0, 16);
        }

        // 1. Kill-switch check
        if (! $this->policyResolver->isEnabled()) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_DISABLED', 'kill_switch_active',
                'MCP operations are disabled via BRAIN_DISABLE_MCP.',
                'Unset BRAIN_DISABLE_MCP to enable.',
                $requestId
            );
        }

        // 2. Resolve registry
        try {
            $registry = $this->registryResolver->resolve();
        } catch (RuntimeException $e) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_REGISTRY_ERROR', 'resolution_failed',
                $e->getMessage(),
                'Ensure the registry file exists and is valid.',
                $requestId
            );
        }

        // 3. Find server
        $serverEntry = null;
        foreach ($registry->servers as $server) {
            if ($server['id'] === $request->serverId) {
                $serverEntry = $server;
                break;
            }
        }

        if ($serverEntry === null) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_SERVER_NOT_FOUND', 'registry_missing_id',
                "Server '{$request->serverId}' not found in registry.",
                'Check mcp-registry.json for available servers.',
                $requestId
            );
        }

        if (! ($serverEntry['enabled'])) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_SERVER_DISABLED', 'server_not_enabled',
                "Server '{$request->serverId}' is disabled in registry.",
                'Enable the server in mcp-registry.json.',
                $requestId
            );
        }

        // 4. Policy check
        if (! $this->policyResolver->isAllowed($request->serverId, $request->tool)) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_CALL_BLOCKED', 'tool_not_allowed',
                "Tool '{$request->tool}' on server '{$request->serverId}' is not in the external tools allowlist.",
                "Run: brain mcp:list ; brain mcp:describe --server={$request->serverId}",
                $requestId
            );
        }

        // 5. Execute
        $class = $serverEntry['class'];
        if (! class_exists($class)) {
            $this->ensureRootAutoloader();
        }

        if (! class_exists($class)) {
             return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_CLASS_NOT_FOUND', 'autoload_failure',
                "Class '{$class}' for server '{$request->serverId}' not found.",
                'Ensure the class is autoloadable.',
                $requestId
            );
        }

        if (! is_subclass_of($class, StdioMcp::class)) {
             return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_UNSUPPORTED_TYPE', 'only_stdio_supported',
                "Server '{$request->serverId}' does not use StdioMcp.",
                'Currently only stdio-based servers are supported for mcp:call.',
                $requestId
            );
        }

        /** @var class-string<StdioMcp> $class */
        $command = $class::defaultCommand();
        $args = $class::defaultArgs();

        // Prepare JSON-RPC request (simplified for v1)
        $rpcRequest = [
            'jsonrpc' => '2.0',
            'id' => uniqid('brain-', true),
            'method' => 'tools/call',
            'params' => [
                'name' => $request->tool,
                'arguments' => $request->input,
            ],
        ];

        // Ensure we are in project root for execution
        $process = new Process([$command, ...$args], $this->projectRoot);
        $process->setInput(json_encode($rpcRequest, JSON_THROW_ON_ERROR) . "\n");
        
        try {
            $process->run();
        } catch (\Throwable $e) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_EXECUTION_FAILED', 'process_spawn_error',
                $e->getMessage(),
                'Check if the server command is available in PATH.',
                $requestId
            );
        }

        if (! $process->isSuccessful()) {
             return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_SERVER_ERROR', 'process_exit_non_zero',
                trim($process->getErrorOutput() ?: "Process exited with code {$process->getExitCode()}"),
                'Check server logs for details.',
                $requestId
            );
        }

        $output = trim($process->getOutput());
        
        // MCP servers might output multiple lines, we look for the JSON-RPC response
        $lines = explode("\n", $output);
        $rpcResponse = null;
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
                $request->serverId, $request->tool,
                'MCP_INVALID_RESPONSE', 'json_rpc_not_found',
                "Server output did not contain a valid JSON-RPC response. Raw: " . substr($output, 0, 100),
                'Ensure the MCP server follows the JSON-RPC spec.',
                $requestId
            );
        }

        // Handle JSON-RPC level errors
        if (isset($rpcResponse['error'])) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_TOOL_ERROR', 'server_returned_json_rpc_error',
                $rpcResponse['error']['message'] ?? 'Unknown error',
                'Check tool arguments and server state.',
                $requestId
            );
        }

        // Redact and return
        $resultData = $rpcResponse['result'] ?? [];
        $resultData = is_array($resultData) ? $resultData : ['value' => $resultData];
        [$redactedData, $redactionsApplied] = $this->redact($resultData);

        return McpCallResult::success($request->serverId, $request->tool, $redactedData, $requestId, $redactionsApplied);
    }

    /**
     * Redact sensitive information from the result.
     * @return array{0: array, 1: bool}
     */
    private function redact(array $data): array
    {
        $sensitiveKeys = [
            'api_key', 'apikey', 'token', 'secret', 'password', 'auth', 'credential',
            'CONTEXT7_API_KEY', 'ANTHROPIC_API_KEY', 'OPENAI_API_KEY'
        ];

        $applied = false;
        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys, &$applied) {
            foreach ($sensitiveKeys as $sensitive) {
                if (is_string($key) && stripos($key, $sensitive) !== false) {
                    if ($value !== '[REDACTED]') {
                        $value = '[REDACTED]';
                        $applied = true;
                    }
                }
            }
        });

        return [$data, $applied];
    }

    /**
     * Ensure the root autoloader is loaded.
     */
    private function ensureRootAutoloader(): void
    {
        $rootAutoloader = $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (is_file($rootAutoloader)) {
            require_once $rootAutoloader;
        }
    }
}
