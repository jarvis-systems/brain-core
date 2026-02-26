<?php

declare(strict_types=1);

namespace BrainCore\Tests\Unit\Services\McpCall;

use PHPUnit\Framework\TestCase;

/**
 * Tests for mcp:call --dry-run contract.
 * Uses CLI subprocess to verify end-to-end behavior.
 */
class McpCallDryRunContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        // Find project root by looking for cli/bin/brain
        $dir = getcwd() ?: '.';
        for ($i = 0; $i < 10; $i++) {
            if (is_file($dir . '/cli/bin/brain')) {
                $this->root = $dir;
                return;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        // Fallback to standard location
        $this->root = '/Users/xsaven/PhpstormProjects/jarvis-brain-node';
    }

    private function runCli(string $command): string
    {
        $cmd = "php {$this->root}/cli/bin/brain $command 2>/dev/null";
        return shell_exec($cmd) ?: '';
    }

    private function runCliWithStderr(string $command): array
    {
        $stdoutFile = tempnam(sys_get_temp_dir(), 'stdout_');
        $stderrFile = tempnam(sys_get_temp_dir(), 'stderr_');

        $cmd = "php {$this->root}/cli/bin/brain $command 1>$stdoutFile 2>$stderrFile";
        shell_exec($cmd);

        $stdout = file_get_contents($stdoutFile) ?: '';
        $stderr = file_get_contents($stderrFile) ?: '';
        $stderrBytes = strlen($stderr);

        unlink($stdoutFile);
        unlink($stderrFile);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'stderr_bytes' => $stderrBytes];
    }

    public function testDryRunReturnsOk(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");
        $json = json_decode($output, true);

        $this->assertTrue($json['ok'] ?? false, 'dry-run should return ok=true');
    }

    public function testDryRunHasCanonicalOutputStructure(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");
        $json = json_decode($output, true);

        $this->assertArrayHasKey('ok', $json);
        $this->assertArrayHasKey('enabled', $json);
        $this->assertArrayHasKey('kill_switch_env', $json);
        $this->assertArrayHasKey('server', $json);
        $this->assertArrayHasKey('tool', $json);
        $this->assertArrayHasKey('redactions_applied', $json);
        $this->assertArrayHasKey('data', $json);

        $data = $json['data'];
        $this->assertArrayHasKey('command', $data);
        $this->assertArrayHasKey('args', $data);
        $this->assertArrayHasKey('input', $data);
        $this->assertArrayHasKey('transport', $data);
        $this->assertArrayHasKey('would_execute', $data);
    }

    public function testDryRunWouldExecuteIsFalse(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");
        $json = json_decode($output, true);

        $this->assertFalse($json['data']['would_execute'] ?? true, 'would_execute must be false');
    }

    public function testDryRunTransportIsStdio(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");
        $json = json_decode($output, true);

        $this->assertSame('stdio', $json['data']['transport'] ?? '', 'transport must be stdio');
    }

    public function testDryRunStderrIsEmpty(): void
    {
        $result = $this->runCliWithStderr("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");

        $this->assertSame(0, $result['stderr_bytes'], 'stderr must be empty');
    }

    public function testDryRunOutputIsSingleLine(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");

        $lines = substr_count($output, "\n");
        $this->assertLessThanOrEqual(1, $lines, 'output should be single line JSON');
    }

    public function testDryRunBlockedToolReturnsError(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=forbidden-tool --input='{}' --dry-run");
        $json = json_decode($output, true);

        $this->assertFalse($json['ok'] ?? true);
        $this->assertArrayHasKey('error', $json);
        $this->assertSame('MCP_CALL_BLOCKED', $json['error']['code'] ?? '');
    }

    public function testDryRunBlockedToolHasGenericMessage(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=forbidden-tool --input='{}' --dry-run");
        $json = json_decode($output, true);

        $message = $json['error']['message'] ?? '';
        $hint = $json['error']['hint'] ?? '';

        $this->assertStringContainsString('not in the external tools allowlist', strtolower($message));
        $this->assertStringContainsString('brain mcp:list', $hint);
    }

    public function testDryRunInvalidJsonReturnsError(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{not json}' --dry-run");
        $json = json_decode($output, true);

        $this->assertFalse($json['ok'] ?? true);
        $this->assertSame('MCP_CALL_INVALID_INPUT', $json['error']['code'] ?? '');
        $this->assertSame('invalid_json', $json['error']['reason'] ?? '');
    }

    public function testDryRunMissingRequiredReturnsError(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{}' --dry-run");
        $json = json_decode($output, true);

        $this->assertFalse($json['ok'] ?? true);
        $this->assertSame('MCP_CALL_INVALID_INPUT', $json['error']['code'] ?? '');
    }

    public function testDryRunDoesNotLeakAbsolutePathInCommand(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");
        $json = json_decode($output, true);

        $command = $json['data']['command'] ?? '';

        $this->assertStringNotContainsString('/Users/', $command);
        $this->assertStringNotContainsString('/home/', $command);
        $this->assertStringNotContainsString('C:\\', $command);
    }

    public function testDryRunDoesNotLeakAbsolutePathInArgs(): void
    {
        $output = $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");
        $json = json_decode($output, true);

        $args = $json['data']['args'] ?? [];
        $argsString = implode(' ', $args);

        $this->assertStringNotContainsString('/Users/', $argsString);
        $this->assertStringNotContainsString('/home/', $argsString);
    }

    public function testDryRunBudgetNotDecremented(): void
    {
        // Reset budget
        $this->runCli("mcp:budget-reset");

        // Check before
        $beforeOutput = $this->runCli("mcp:guardrails");
        $before = json_decode($beforeOutput, true);
        $beforeRemaining = $before['data']['call_budget']['remaining'] ?? 0;

        // Run dry-run
        $this->runCli("mcp:call --server=context7 --tool=search --input='{\"query\":\"test\"}' --dry-run");

        // Check after
        $afterOutput = $this->runCli("mcp:guardrails");
        $after = json_decode($afterOutput, true);
        $afterRemaining = $after['data']['call_budget']['remaining'] ?? 0;

        $this->assertSame($beforeRemaining, $afterRemaining, 'budget should not be decremented by dry-run');
    }
}
