<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Services\McpCall\McpInputValidator;
use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpRegistry\ResolvedRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class McpInputValidatorTest extends TestCase
{
    private string $tempDir;
    private string $realRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/mcp-input-val-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->realRoot = dirname(__DIR__, 2);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_missing_required_throws(): void
    {
        $registryResolver = $this->createMock(McpRegistryResolver::class);
        $registryResolver->method('resolve')->willReturn(new ResolvedRegistry('1.0.0', [
            [
                'id' => 'vector-task',
                'class' => \BrainNode\Mcp\VectorTaskMcp::class,
                'enabled' => true
            ]
        ]));

        $validator = new McpInputValidator($registryResolver, $this->realRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('reason=schema_validation_failed');
        $this->expectExceptionMessage('Missing required property: title');

        $validator->validate('vector-task', 'task_create', ['content' => 'test']);
    }

    public function test_bad_type_throws(): void
    {
        $registryResolver = $this->createMock(McpRegistryResolver::class);
        $registryResolver->method('resolve')->willReturn(new ResolvedRegistry('1.0.0', [
            [
                'id' => 'vector-task',
                'class' => \BrainNode\Mcp\VectorTaskMcp::class,
                'enabled' => true
            ]
        ]));

        $validator = new McpInputValidator($registryResolver, $this->realRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Property 'task_id' must be integer, got string");

        $validator->validate('vector-task', 'task_get', ['task_id' => 'not-an-int']);
    }

    public function test_valid_input_passes(): void
    {
        $registryResolver = $this->createMock(McpRegistryResolver::class);
        $registryResolver->method('resolve')->willReturn(new ResolvedRegistry('1.0.0', [
            [
                'id' => 'vector-task',
                'class' => \BrainNode\Mcp\VectorTaskMcp::class,
                'enabled' => true
            ]
        ]));

        $validator = new McpInputValidator($registryResolver, $this->realRoot);

        $validator->validate('vector-task', 'task_create', [
            'title' => 'Title',
            'content' => 'Content',
            'order' => 1
        ]);
        
        $this->assertTrue(true);
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
