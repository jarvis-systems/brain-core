<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Services\McpRegistry\FileRegistryResolver;
use PHPUnit\Framework\TestCase;

class McpRegistryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/brain-registry-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    public function test_resolves_from_brain_config_override(): void
    {
        $registryDir = $this->tempDir . '/.brain-config';
        mkdir($registryDir, 0755, true);
        
        $data = [
            'version' => '1.0.0',
            'servers' => [
                ['id' => 'test-server', 'class' => 'TestClass', 'enabled' => true]
            ]
        ];
        file_put_contents($registryDir . '/mcp-registry.json', json_encode($data));

        $resolver = new FileRegistryResolver($this->tempDir, '/tmp/cli');
        $resolved = $resolver->resolve();

        $this->assertEquals('1.0.0', $resolved->version);
        $this->assertCount(1, $resolved->servers);
        $this->assertEquals('test-server', $resolved->servers[0]['id']);
        $this->assertStringContainsString('.brain-config/mcp-registry.json', $resolved->resolvedPath);
    }

    public function test_resolves_from_brain_config_consumer_override(): void
    {
        $registryDir = $this->tempDir . '/.brain/config';
        mkdir($registryDir, 0755, true);
        
        $data = [
            'version' => '1.0.0',
            'servers' => [
                ['id' => 'consumer-server', 'class' => 'ConsumerClass', 'enabled' => false]
            ]
        ];
        file_put_contents($registryDir . '/mcp-registry.json', json_encode($data));

        $resolver = new FileRegistryResolver($this->tempDir, '/tmp/cli');
        $resolved = $resolver->resolve();

        $this->assertEquals('consumer-server', $resolved->servers[0]['id']);
        $this->assertStringContainsString('.brain/config/mcp-registry.json', $resolved->resolvedPath);
    }

    public function test_resolves_from_cli_default(): void
    {
        $cliDir = $this->tempDir . '/cli';
        mkdir($cliDir, 0755, true);
        
        $data = [
            'version' => '1.0.0',
            'servers' => [
                ['id' => 'default-server', 'class' => 'DefaultClass', 'enabled' => true]
            ]
        ];
        file_put_contents($cliDir . '/mcp-registry.json', json_encode($data));

        $resolver = new FileRegistryResolver($this->tempDir, $cliDir);
        $resolved = $resolver->resolve();

        $this->assertEquals('default-server', $resolved->servers[0]['id']);
        $this->assertStringContainsString('cli/mcp-registry.json', $resolved->resolvedPath);
    }

    public function test_fails_when_missing_registry(): void
    {
        $resolver = new FileRegistryResolver($this->tempDir, '/tmp/cli');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MCP_REGISTRY_MISSING');
        
        $resolver->resolve();
    }

    public function test_fails_on_invalid_version(): void
    {
        $registryDir = $this->tempDir . '/.brain-config';
        mkdir($registryDir, 0755, true);
        
        $data = [
            'version' => '2.0.0',
            'servers' => []
        ];
        file_put_contents($registryDir . '/mcp-registry.json', json_encode($data));

        $resolver = new FileRegistryResolver($this->tempDir, '/tmp/cli');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported registry version');
        
        $resolver->resolve();
    }

    public function test_fails_on_duplicate_ids(): void
    {
        $registryDir = $this->tempDir . '/.brain-config';
        mkdir($registryDir, 0755, true);
        
        $data = [
            'version' => '1.0.0',
            'servers' => [
                ['id' => 'dup', 'class' => 'C1', 'enabled' => true],
                ['id' => 'dup', 'class' => 'C2', 'enabled' => true],
            ]
        ];
        file_put_contents($registryDir . '/mcp-registry.json', json_encode($data));

        $resolver = new FileRegistryResolver($this->tempDir, '/tmp/cli');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Duplicate server ID 'dup'");
        
        $resolver->resolve();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
