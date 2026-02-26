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
        private readonly \BrainCore\Contracts\McpToolPolicy\McpToolPolicyResolver $toolPolicyResolver,
        private readonly string $projectRoot,
        private readonly ?McpCallBudget $budget = null,
        private readonly ?McpCallRetryPolicy $retryPolicy = null,
        private readonly ?ErrorNormalizer $errorNormalizer = null,
    ) {
    }

    /**
     * Execute an MCP call.
     */
    public function execute(McpCallRequest $request, bool $trace = false, bool $dryRun = false): McpCallResult|array
    {
        $requestId = null;
        if ($trace) {
            $stableInput = json_encode($request->input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $requestId = substr(hash('sha256', $request->serverId . '|' . $request->tool . '|' . $stableInput), 0, 16);
        }

        // 1. Kill-switch check
        if (!$this->toolPolicyResolver->isEnabled() || !$this->policyResolver->isEnabled()) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_DISABLED',
                'kill_switch_active',
                'MCP operations are disabled via BRAIN_DISABLE_MCP.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        // 1.5 Portability rule: block mock-echo in normal mode
        if ($request->serverId === 'mock-echo' && getenv('BRAIN_TEST_MODE') !== '1') {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_SERVER_NOT_FOUND',
                'test_server_not_available',
                'Server mock-echo is a test stub and is not available in normal execution mode.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        // 2. Budget check
        $budget = $this->budget ?? McpCallBudget::create($this->projectRoot);
        if ($budget->isExhausted()) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_CALL_BLOCKED',
                'budget_exhausted',
                'MCP call budget exhausted.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        // 3. Resolve registry
        try {
            $registry = $this->registryResolver->resolve();
        } catch (RuntimeException $e) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_REGISTRY_ERROR',
                'resolution_failed',
                'Failed to resolve MCP registry.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        // 4. Find server
        $serverEntry = null;
        foreach ($registry->servers as $server) {
            if ($server['id'] === $request->serverId) {
                $serverEntry = $server;
                break;
            }
        }

        if ($serverEntry === null) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_SERVER_NOT_FOUND',
                'registry_missing_id',
                'Requested MCP server not found in registry.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        if (!($serverEntry['enabled'])) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_SERVER_DISABLED',
                'server_not_enabled',
                'Requested MCP server is disabled in registry.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        // 5. Policy check
        if (!$this->policyResolver->isAllowed($request->serverId, $request->tool)) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_CALL_BLOCKED',
                'tool_not_allowed',
                'Requested tool is not in the external tools allowlist.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        // 6. Execute with retries
        $retryPolicy = $this->retryPolicy ?? new McpCallRetryPolicy();
        $normalizer = $this->errorNormalizer ?? new ErrorNormalizer();
        $attempts = 0;

        // Logical budget: decrement once per intent
        if (!$dryRun) {
            $budget->recordCall();
        }

        while (true) {
            $attempts++;
            $result = $this->performCall($request, $serverEntry, $requestId, $normalizer, $dryRun);

            if ($dryRun || $result->ok) {
                return $result;
            }

            if (!$retryPolicy->shouldRetry($result, $attempts)) {
                return $result;
            }

            usleep($retryPolicy->getBackoffMicroseconds($attempts));
        }
    }

    /**
     * Perform the actual call execution.
     * @return McpCallResult|array
     */
    private function performCall(McpCallRequest $request, array $serverEntry, ?string $requestId, ErrorNormalizer $normalizer, bool $dryRun)
    {
        $class = $serverEntry['class'];
        if (!class_exists($class)) {
            $this->ensureRootAutoloader();
        }

        if (!class_exists($class)) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_CLASS_NOT_FOUND',
                'autoload_failure',
                'MCP server class not found.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
                $requestId
            );
        }

        if (!is_subclass_of($class, StdioMcp::class)) {
            return McpCallResult::error(
                $request->serverId,
                $request->tool,
                'MCP_UNSUPPORTED_TYPE',
                'only_stdio_supported',
                'Server does not support stdio transport.',
                'Run: brain mcp:list ; brain mcp:describe --server=<server>',
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

        $adapter = new StdioAdapter($this->projectRoot, $normalizer);

        if ($dryRun) {
            return $adapter->resolveCommand([$command, ...$args], $request->serverId, $request->tool, $request->input);
        }

        return $adapter->execute(
            [$command, ...$args],
            $rpcRequest,
            $request->serverId,
            $request->tool,
            $requestId
        );
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
