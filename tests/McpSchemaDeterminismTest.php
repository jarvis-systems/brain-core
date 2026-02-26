<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Mcp\Schemas\VectorMemorySchema;
use BrainCore\Mcp\Schemas\VectorTaskSchema;
use PHPUnit\Framework\TestCase;

/**
 * Validates deterministic ordering of MCP schema definitions.
 *
 * Every schema must satisfy:
 * 1. Tool names sorted ASC
 * 2. 'required' arrays sorted ASC
 * 3. 'allowed' arrays sorted ASC
 * 4. 'types' keys sorted ASC
 * 5. Every tool has description, required, allowed, types keys
 */
class McpSchemaDeterminismTest extends TestCase
{
    /**
     * @return array<string, array{class-string, string}>
     */
    public static function schemaProvider(): array
    {
        return [
            'VectorMemorySchema' => [VectorMemorySchema::class, 'VectorMemorySchema'],
            'VectorTaskSchema' => [VectorTaskSchema::class, 'VectorTaskSchema'],
        ];
    }

    /**
     * @dataProvider schemaProvider
     */
    public function testToolNamesSortedAsc(string $class, string $label): void
    {
        $schema = $class::get();
        $keys = array_keys($schema);
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys, "$label: tool names must be sorted ASC");
    }

    /**
     * @dataProvider schemaProvider
     */
    public function testRequiredSortedAsc(string $class, string $label): void
    {
        $schema = $class::get();

        foreach ($schema as $toolName => $meta) {
            $required = $meta['required'] ?? [];
            $sorted = $required;
            sort($sorted);

            $this->assertSame($sorted, $required, "$label.$toolName: 'required' must be sorted ASC");
        }
    }

    /**
     * @dataProvider schemaProvider
     */
    public function testAllowedSortedAsc(string $class, string $label): void
    {
        $schema = $class::get();

        foreach ($schema as $toolName => $meta) {
            $allowed = $meta['allowed'] ?? [];
            $sorted = $allowed;
            sort($sorted);

            $this->assertSame($sorted, $allowed, "$label.$toolName: 'allowed' must be sorted ASC");
        }
    }

    /**
     * @dataProvider schemaProvider
     */
    public function testTypesKeysSortedAsc(string $class, string $label): void
    {
        $schema = $class::get();

        foreach ($schema as $toolName => $meta) {
            $types = $meta['types'] ?? [];
            $keys = array_keys($types);
            $sorted = $keys;
            sort($sorted);

            $this->assertSame($sorted, $keys, "$label.$toolName: 'types' keys must be sorted ASC");
        }
    }

    /**
     * @dataProvider schemaProvider
     */
    public function testEveryToolHasCanonicalKeys(string $class, string $label): void
    {
        $schema = $class::get();
        $requiredKeys = ['description', 'required', 'allowed', 'types'];

        foreach ($schema as $toolName => $meta) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $meta, "$label.$toolName: missing canonical key '$key'");
            }
        }
    }
}
