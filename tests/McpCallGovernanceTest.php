<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Contracts\McpCall\McpCallRequest;
use BrainCore\Contracts\McpExternalToolsPolicy\McpExternalToolsPolicyResolver;
use BrainCore\Contracts\McpExternalToolsPolicy\ResolvedExternalToolsPolicy;
use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpRegistry\ResolvedRegistry;
use BrainCore\Contracts\McpToolPolicy\McpToolPolicyResolver;
use BrainCore\Contracts\McpToolPolicy\ResolvedPolicy;
use BrainCore\Services\McpCall\McpCallExecutor;
use PHPUnit\Framework\TestCase;

class McpCallGovernanceTest extends TestCase
{
    private function createRegistryResolver(array $servers = []): McpRegistryResolver
    {
        return new class ($servers) implements McpRegistryResolver {
            public function __construct(private readonly array $servers)
            {}
            public function resolve(): ResolvedRegistry
            {
                return new ResolvedRegistry('1.0.0', $this->servers, '/tmp');
            }
        };
    }

    private function createExternalToolsPolicyResolver(bool $enabled = true, array $servers = []): McpExternalToolsPolicyResolver
    {
        return new class ($enabled, $servers) implements McpExternalToolsPolicyResolver {
            public function __construct(private readonly bool $enabled, private readonly array $servers)
            {}
            public function resolve(): ResolvedExternalToolsPolicy
            {
                return new ResolvedExternalToolsPolicy($this->enabled, '1.0.0', 'ENV', $this->servers, '/tmp');
            }
            public function isAllowed(string $serverId, string $tool): bool
            {
                return true; }
            public function isEnabled(): bool
            {
                return $this->enabled; }
        };
    }

    private function createToolPolicyResolver(bool $enabled = true): McpToolPolicyResolver
    {
        return new class ($enabled) implements McpToolPolicyResolver {
            public function __construct(private readonly bool $enabled)
            {}
            public function resolve(): ResolvedPolicy
            {
                return $this->enabled ? new ResolvedPolicy(true, '1.0.0', 'ENV', [], [], [], '/tmp') : ResolvedPolicy::disabled('ENV');
            }
            public function isAllowed(string $tool): bool
            {
                return true; }
            public function isNever(string $tool): bool
            {
                return false; }
            public function isEnabled(): bool
            {
                return $this->enabled; }
        };
    }

    public function test_tool_policy_kill_switch_blocks_execution(): void
    {
        $executor = new McpCallExecutor(
            $this->createRegistryResolver(),
            $this->createExternalToolsPolicyResolver(true),
            $this->createToolPolicyResolver(false), // Disabled here
            '/tmp'
        );

        $request = new McpCallRequest('test', 'tool', []);
        $result = $executor->execute($request);

        $this->assertFalse($result->ok);
        $this->assertEquals('MCP_DISABLED', $result->error['code']);
        $this->assertEquals('kill_switch_active', $result->error['reason']);
        $this->assertStringContainsString('brain mcp:list', $result->error['hint']);
    }

    public function test_external_tools_kill_switch_blocks_execution(): void
    {
        $executor = new McpCallExecutor(
            $this->createRegistryResolver(),
            $this->createExternalToolsPolicyResolver(false), // Disabled here
            $this->createToolPolicyResolver(true),
            '/tmp'
        );

        $request = new McpCallRequest('test', 'tool', []);
        $result = $executor->execute($request);

        $this->assertFalse($result->ok);
        $this->assertEquals('MCP_DISABLED', $result->error['code']);
        $this->assertEquals('kill_switch_active', $result->error['reason']);
    }

    public function test_secret_redaction_does_not_leak(): void
    {
        $data = [
            'normal_key' => 'normal_value',
            'api_key' => 'super_secret_123',
            'nested' => [
                'token' => 'nested_secret',
                'safe' => 'safe_val'
            ]
        ];

        [$redactedData, $redactionsApplied] = \BrainCore\Services\McpCall\McpRedactor::redactArray($data);

        $this->assertTrue($redactionsApplied);
        $this->assertEquals('normal_value', $redactedData['normal_key']);
        $this->assertEquals('[REDACTED]', $redactedData['api_key']);
        $this->assertEquals('[REDACTED]', $redactedData['nested']['token']);
        $this->assertEquals('safe_val', $redactedData['nested']['safe']);
    }
    public function test_dx_safe_debug_mode_conditionally_returns_redacted_info(): void
    {
        $normalizer = new \BrainCore\Services\McpCall\ErrorNormalizer();
        $secret = 's' . 'k-ant-' . '12345678901234567890';
        $secretException = new \RuntimeException('Failed with secret ' . $secret . ' and Bearer eyJhbGci... here.');

        // 1. Default mode
        putenv('BRAIN_MCP_DEBUG=');
        putenv('BRAIN_DEBUG_MCP=');
        putenv('BRAIN_MCP_DEBUG_VERBOSE=');
        $result1 = $normalizer->normalizeThrowable($secretException, 'test', 'tool', 'req-1');
        $this->assertArrayNotHasKey('debug', $result1->toStableArray()['error']);

        // 2. Debug mode (no verbose)
        putenv('BRAIN_MCP_DEBUG=1');
        $result2 = $normalizer->normalizeThrowable($secretException, 'test', 'tool', 'req-1');
        $stable2 = $result2->toStableArray();
        $this->assertArrayHasKey('debug', $stable2['error']);
        $this->assertEquals('RuntimeException', $stable2['error']['debug']['exception_class']);
        $this->assertArrayNotHasKey('message', $stable2['error']['debug']);

        // 3. Verbose mode
        putenv('BRAIN_MCP_DEBUG_VERBOSE=1');
        $result3 = $normalizer->normalizeThrowable($secretException, 'test', 'tool', 'req-1');
        $stable3 = $result3->toStableArray();
        $this->assertArrayHasKey('message', $stable3['error']['debug']);

        // Assert redaction worked!
        $this->assertStringNotContainsString($secret, $stable3['error']['debug']['message']);
        $this->assertStringContainsString('[REDACTED]', $stable3['error']['debug']['message']);

        // Cleanup env
        putenv('BRAIN_MCP_DEBUG=');
        putenv('BRAIN_MCP_DEBUG_VERBOSE=');
    }
}
