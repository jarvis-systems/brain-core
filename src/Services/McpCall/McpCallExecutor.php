<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use BrainCore\Contracts\McpCall\McpCallRequest;
use BrainCore\Contracts\McpCall\McpCallResult;
use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpToolPolicy\McpToolPolicyResolver;
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
        private readonly McpToolPolicyResolver $policyResolver,
        private readonly string $projectRoot,
    ) {}

    /**
     * Execute an MCP call.
     */
    public function execute(McpCallRequest $request): McpCallResult
    {
        // 1. Kill-switch check
        if (! $this->policyResolver->isEnabled()) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_DISABLED', 'kill_switch_active',
                'MCP operations are disabled via BRAIN_DISABLE_MCP.',
                'Unset BRAIN_DISABLE_MCP to enable.'
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
                'Ensure the registry file exists and is valid.'
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
                'Check mcp-registry.json for available servers.'
            );
        }

        if (! ($serverEntry['enabled'])) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_SERVER_DISABLED', 'server_not_enabled',
                "Server '{$request->serverId}' is disabled in registry.",
                'Enable the server in mcp-registry.json.'
            );
        }

        // 4. Policy check
        if (! $this->policyResolver->isAllowed($request->tool)) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_CALL_BLOCKED', 'tool_not_allowed',
                "Tool '{$request->tool}' is not in the allowlist.",
                'Add the tool to mcp-tools.allowlist.json.'
            );
        }

        if ($this->policyResolver->isNever($request->tool)) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_CALL_BLOCKED', 'tool_explicitly_forbidden',
                "Tool '{$request->tool}' is explicitly forbidden.",
                'Check the "never" section in mcp-tools.allowlist.json.'
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
                'Ensure the class is autoloadable.'
            );
        }

        if (! is_subclass_of($class, StdioMcp::class)) {
             return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_UNSUPPORTED_TYPE', 'only_stdio_supported',
                "Server '{$request->serverId}' does not use StdioMcp.",
                'Currently only stdio-based servers are supported for mcp:call.'
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
        $process->setInput(json_encode($rpcRequest, JSON_THROW_ON_ERROR) . "
");
        
        try {
            $process->run();
        } catch (\Throwable $e) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_EXECUTION_FAILED', 'process_spawn_error',
                $e->getMessage(),
                'Check if the server command is available in PATH.'
            );
        }

        if (! $process->isSuccessful()) {
             return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_SERVER_ERROR', 'process_exit_non_zero',
                trim($process->getErrorOutput() ?: "Process exited with code {$process->getExitCode()}"),
                'Check server logs for details.'
            );
        }

        $output = trim($process->getOutput());
        
        // MCP servers might output multiple lines, we look for the JSON-RPC response
        $lines = explode("
", $output);
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
                'Ensure the MCP server follows the JSON-RPC spec.'
            );
        }

        // Handle JSON-RPC level errors
        if (isset($rpcResponse['error'])) {
            return McpCallResult::error(
                $request->serverId, $request->tool,
                'MCP_TOOL_ERROR', 'server_returned_json_rpc_error',
                $rpcResponse['error']['message'] ?? 'Unknown error',
                'Check tool arguments and server state.'
            );
        }

        // Redact and return
        $resultData = $rpcResponse['result'] ?? [];
        $redactedData = $this->redact(is_array($resultData) ? $resultData : ['value' => $resultData]);

        return McpCallResult::success($request->serverId, $request->tool, $redactedData);
    }

    /**
     * Redact sensitive information from the result.
     */
    private function redact(array $data): array
    {
        $sensitiveKeys = [
            'api_key', 'apikey', 'token', 'secret', 'password', 'auth', 'credential',
            'CONTEXT7_API_KEY', 'ANTHROPIC_API_KEY', 'OPENAI_API_KEY'
        ];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (is_string($key) && stripos($key, $sensitive) !== false) {
                    $value = '[REDACTED]';
                }
            }
        });

        return $data;
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
