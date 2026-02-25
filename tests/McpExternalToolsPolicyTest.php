<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Contracts\McpExternalToolsPolicy\ResolvedExternalToolsPolicy;
use BrainCore\Services\McpExternalToolsPolicy\FileExternalToolsPolicyResolver;
use PHPUnit\Framework\TestCase;

class McpExternalToolsPolicyTest extends TestCase
{
    private string $tempDir;
    private string $cliDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/mcp-ext-policy-test-' . uniqid();
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
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-external-tools.allowlist.json', ['servers' => ['s1' => ['enabled' => true, 'tools_allowed' => ['t1']]]]);
        $this->writePolicy($this->tempDir . '/.brain/config/mcp-external-tools.allowlist.json', ['servers' => ['s2' => ['enabled' => true, 'tools_allowed' => ['t2']]]]);
        $this->writePolicy($this->cliDir . '/mcp-external-tools.allowlist.json', ['servers' => ['s3' => ['enabled' => true, 'tools_allowed' => ['t3']]]]);

        $resolver = new FileExternalToolsPolicyResolver($this->tempDir, $this->cliDir);
        $policy = $resolver->resolve();

        $this->assertTrue($policy->enabled);
        $this->assertArrayHasKey('s1', $policy->servers);
        $this->assertStringContainsString('.brain-config', $policy->resolvedPath);
    }

    public function test_kill_switch_blocks_all(): void
    {
        putenv('BRAIN_DISABLE_MCP=true');
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-external-tools.allowlist.json', ['servers' => ['s1' => ['enabled' => true, 'tools_allowed' => ['t1']]]]);

        $resolver = new FileExternalToolsPolicyResolver($this->tempDir, $this->cliDir);
        
        $this->assertFalse($resolver->isEnabled());
        $this->assertFalse($resolver->isAllowed('s1', 't1'));
    }

    public function test_is_allowed_logic(): void
    {
        $this->writePolicy($this->tempDir . '/.brain-config/mcp-external-tools.allowlist.json', [
            'servers' => [
                'vector-memory' => [
                    'enabled' => true,
                    'tools_allowed' => ['search', 'stats']
                ],
                'disabled-server' => [
                    'enabled' => false,
                    'tools_allowed' => ['any']
                ]
            ]
        ]);

        $resolver = new FileExternalToolsPolicyResolver($this->tempDir, $this->cliDir);

        $this->assertTrue($resolver->isAllowed('vector-memory', 'search'));
        $this->assertTrue($resolver->isAllowed('vector-memory', 'stats'));
        
        $this->assertFalse($resolver->isAllowed('vector-memory', 'upsert')); // Not in list
        $this->assertFalse($resolver->isAllowed('disabled-server', 'any')); // Server disabled
        $this->assertFalse($resolver->isAllowed('unknown-server', 'any')); // Server missing
    }

    public function test_invalid_json_throws(): void
    {
        file_put_contents($this->tempDir . '/.brain-config/mcp-external-tools.allowlist.json', 'not json');
        $resolver = new FileExternalToolsPolicyResolver($this->tempDir, $this->cliDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $resolver->resolve();
    }

    private function writePolicy(string $path, array $overrides = []): void
    {
        $default = [
            'schema_version' => '1.0.0',
            'kill_switch_env' => 'BRAIN_DISABLE_MCP',
            'servers' => [],
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
