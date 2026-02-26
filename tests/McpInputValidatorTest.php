<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Contracts\McpExternalToolsPolicy\McpExternalToolsPolicyResolver;
use BrainCore\Contracts\McpExternalToolsPolicy\ResolvedExternalToolsPolicy;
use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpRegistry\ResolvedRegistry;
use BrainCore\Services\McpCall\McpInputValidator;
use BrainCore\Services\McpDiscovery\McpDiscoveryService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A fake MCP stub class that provides a schema() method and is autoloadable
 * within the core test suite (unlike BrainNode\Mcp\VectorTaskMcp which lives
 * in node/ and is not on the core autoload path).
 */
class FakeSchemaMcp
{
    public static function schema(): array
    {
        return [
            'task_create' => [
                'required' => ['title', 'content'],
                'allowed' => ['title', 'content', 'order'],
                'types' => [
                    'title' => 'string',
                    'content' => 'string',
                    'order' => 'integer',
                ],
            ],
            'task_get' => [
                'required' => ['task_id'],
                'allowed' => ['task_id'],
                'types' => [
                    'task_id' => 'integer',
                ],
            ],
        ];
    }
}

class McpInputValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/mcp-input-val-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDiscoveryService(
        string $fakeClass,
        array $toolsAllowed
    ): McpDiscoveryService {
        $registryResolver = $this->createMock(McpRegistryResolver::class);
        $registryResolver->method('resolve')->willReturn(new ResolvedRegistry('1.0.0', [
            [
                'id' => 'fake-server',
                'class' => $fakeClass,
                'enabled' => true,
            ],
        ]));

        $policyResolver = $this->createMock(McpExternalToolsPolicyResolver::class);
        $policyResolver->method('isEnabled')->willReturn(true);
        $policyResolver->method('resolve')->willReturn(new ResolvedExternalToolsPolicy(
            enabled: true,
            version: '1.0.0',
            killSwitchEnv: 'BRAIN_DISABLE_MCP',
            servers: [
                'fake-server' => ['enabled' => true, 'tools_allowed' => $toolsAllowed],
            ]
        ));

        return new McpDiscoveryService($registryResolver, $policyResolver);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_missing_required_throws(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_create']);
        $validator = new McpInputValidator($discovery);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/reason=schema_validation_failed/');
        $this->expectExceptionMessageMatches('/Input missing a required property/');

        // 'title' is missing — should throw
        $validator->validate('fake-server', 'task_create', ['content' => 'test']);
    }

    public function test_bad_type_throws(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_get']);
        $validator = new McpInputValidator($discovery);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Property type mismatch/');

        // task_id must be integer, we're passing a string — should throw
        $validator->validate('fake-server', 'task_get', ['task_id' => 'not-an-int']);
    }

    public function test_valid_input_passes(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_create']);
        $validator = new McpInputValidator($discovery);

        // Both required fields present, types correct — should NOT throw
        $validator->validate('fake-server', 'task_create', [
            'title' => 'Title',
            'content' => 'Content',
            'order' => 1,
        ]);

        $this->assertTrue(true);
    }

    public function test_error_has_canonical_hint(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_get']);
        $validator = new McpInputValidator($discovery);

        try {
            $validator->validate('fake-server', 'task_get', []);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $this->assertStringContainsString('Run: brain mcp:list ; brain mcp:describe --server=<server>', $msg);
            $this->assertStringContainsString('code=MCP_CALL_INVALID_INPUT', $msg);
            $this->assertStringContainsString('reason=schema_validation_failed', $msg);
        }
    }

    public function test_missing_required_message_is_generic(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_get']);
        $validator = new McpInputValidator($discovery);

        try {
            $validator->validate('fake-server', 'task_get', []);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            // Must NOT contain property name in message
            $this->assertStringContainsString('message="Input missing a required property."', $msg);
            $this->assertStringNotContainsString('task_id', $msg);
        }
    }

    public function test_type_mismatch_message_is_generic(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_get']);
        $validator = new McpInputValidator($discovery);

        try {
            $validator->validate('fake-server', 'task_get', ['task_id' => 'not-an-int']);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            // Must NOT contain property name in message
            $this->assertStringContainsString('message="Property type mismatch."', $msg);
            $this->assertStringContainsString('code=MCP_CALL_INVALID_INPUT', $msg);
            $this->assertStringContainsString('reason=schema_validation_failed', $msg);
        }
    }

    public function test_error_code_is_mcp_call_invalid_input(): void
    {
        $discovery = $this->makeDiscoveryService(FakeSchemaMcp::class, ['task_get']);
        $validator = new McpInputValidator($discovery);

        try {
            $validator->validate('fake-server', 'task_get', []);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('code=MCP_CALL_INVALID_INPUT', $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
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
