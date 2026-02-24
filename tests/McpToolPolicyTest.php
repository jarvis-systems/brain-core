<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Contracts\McpToolPolicy\ResolvedPolicy;
use BrainCore\Services\McpToolPolicy\FilePolicyResolver;
use PHPUnit\Framework\TestCase;

class McpToolPolicyTest extends TestCase
{
    private string $tempDir;

    private string $cliDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/mcp-policy-test-' . uniqid();
        $this->cliDir = $this->tempDir . '/cli';
        mkdir($this->tempDir . '/.brain-config', 0755, true);
        mkdir($this->tempDir . '/.brain/config', 0755, true);
        mkdir($this->cliDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        putenv('BRAIN_DISABLE_MCP');
        parent::tearDown();
    }

    public function test_resolves_brain_config_first(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', ['allowed' => ['docs']]);
        $this->writePolicy($this->tempDir . '/.brain/config/mcp-tools.allowlist.json', ['allowed' => ['status']]);
        $this->writePolicy($this->cliDir . '/mcp-tools.allowlist.json', ['allowed' => ['list']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);
        $policy = $resolver->resolve();

        $this->assertTrue($policy->enabled);
        $this->assertEquals(['docs'], $policy->allowed);
        $this->assertStringContainsString('.brain-config', $policy->resolvedPath);
    }

    public function test_resolves_brain_config_second(): void
    {
        $this->writePolicy($this->tempDir . '/.brain/config/mcp-tools.allowlist.json', ['allowed' => ['status']]);
        $this->writePolicy($this->cliDir . '/mcp-tools.allowlist.json', ['allowed' => ['list']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);
        $policy = $resolver->resolve();

        $this->assertEquals(['status'], $policy->allowed);
        $this->assertStringContainsString('.brain/config', $policy->resolvedPath);
    }

    public function test_resolves_cli_default_last(): void
    {
        $this->writePolicy($this->cliDir . '/mcp-tools.allowlist.json', ['allowed' => ['list']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);
        $policy = $resolver->resolve();

        $this->assertEquals(['list'], $policy->allowed);
        $this->assertStringContainsString('cli', $policy->resolvedPath);
    }

    public function test_throws_when_no_policy_found(): void
    {
        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No MCP tool policy file found');

        $resolver->resolve();
    }

    public function test_overlap_validation_fails(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'allowed' => ['docs', 'compile'],
            'never' => ['compile', 'init'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('overlap');

        $resolver->resolve();
    }

    public function test_kill_switch_returns_disabled(): void
    {
        putenv('BRAIN_DISABLE_MCP=true');
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', ['allowed' => ['docs']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);
        $policy = $resolver->resolve();

        $this->assertFalse($policy->enabled);
        $this->assertEmpty($policy->allowed);
        $this->assertNull($policy->resolvedPath);
    }

    public function test_kill_switch_value_1(): void
    {
        putenv('BRAIN_DISABLE_MCP=1');
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', ['allowed' => ['docs']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertFalse($resolver->isEnabled());
    }

    public function test_kill_switch_value_false_allows_policy(): void
    {
        putenv('BRAIN_DISABLE_MCP=false');
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', ['allowed' => ['docs']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertTrue($resolver->isEnabled());
    }

    public function test_wildcard_never_blocks_prefix(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'never' => ['make:*'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertTrue($resolver->isNever('make:command'));
        $this->assertTrue($resolver->isNever('make:master'));
        $this->assertTrue($resolver->isNever('make:include'));
    }

    public function test_wildcard_does_not_block_different_prefix(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'never' => ['make:*'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertFalse($resolver->isNever('compile'));
        $this->assertFalse($resolver->isNever('maker'));
    }

    public function test_exact_match_never(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'never' => ['compile'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertTrue($resolver->isNever('compile'));
        $this->assertFalse($resolver->isNever('compile:force'));
    }

    public function test_is_allowed_true(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'allowed' => ['docs', 'diagnose'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertTrue($resolver->isAllowed('docs'));
        $this->assertTrue($resolver->isAllowed('diagnose'));
    }

    public function test_is_allowed_false(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'allowed' => ['docs'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertFalse($resolver->isAllowed('compile'));
        $this->assertFalse($resolver->isAllowed('init'));
    }

    public function test_is_allowed_returns_false_when_kill_switch_active(): void
    {
        putenv('BRAIN_DISABLE_MCP=true');
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'allowed' => ['docs'],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->assertFalse($resolver->isAllowed('docs'));
    }

    public function test_invalid_json_throws(): void
    {
        file_put_contents($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', 'not json');

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $resolver->resolve();
    }

    public function test_missing_version_throws(): void
    {
        file_put_contents($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', json_encode([
            'allowed' => ['docs'],
            'never' => [],
        ]));

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('version');

        $resolver->resolve();
    }

    public function test_unsupported_version_throws(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'version' => '2.0.0',
            'allowed' => ['docs'],
            'never' => [],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported policy version');

        $resolver->resolve();
    }

    public function test_policy_caching(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', ['allowed' => ['docs']]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);
        $policy1 = $resolver->resolve();
        $policy2 = $resolver->resolve();

        $this->assertSame($policy1, $policy2);
    }

    public function test_resolved_policy_dto(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-tools.allowlist.json', [
            'version' => '1.0.0',
            'kill_switch_env' => 'BRAIN_DISABLE_MCP',
            'allowed' => ['docs', 'diagnose'],
            'never' => ['compile'],
            'clients' => ['claude' => ['enabled' => true]],
        ]);

        $resolver = new FilePolicyResolver($this->tempDir, $this->cliDir);
        $policy = $resolver->resolve();

        $this->assertEquals('1.0.0', $policy->version);
        $this->assertEquals('BRAIN_DISABLE_MCP', $policy->killSwitchEnv);
        $this->assertEquals(['docs', 'diagnose'], $policy->allowed);
        $this->assertEquals(['compile'], $policy->never);
        $this->assertEquals(['claude' => ['enabled' => true]], $policy->clients);
    }

    public function test_disabled_policy_factory(): void
    {
        $policy = ResolvedPolicy::disabled('CUSTOM_DISABLE');

        $this->assertFalse($policy->enabled);
        $this->assertEmpty($policy->allowed);
        $this->assertEmpty($policy->never);
        $this->assertEquals('CUSTOM_DISABLE', $policy->killSwitchEnv);
    }

    private function writePolicy(string $path, array $overrides = []): void
    {
        $default = [
            'version' => '1.0.0',
            'kill_switch_env' => 'BRAIN_DISABLE_MCP',
            'allowed' => [],
            'never' => [],
            'clients' => [],
        ];

        $data = array_merge($default, $overrides);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
