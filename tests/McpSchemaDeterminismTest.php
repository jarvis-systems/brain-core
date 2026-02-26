<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Mcp\Schemas\Context7Schema;
use BrainCore\Mcp\Schemas\SequentialThinkingSchema;
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
            'Context7Schema' => [Context7Schema::class, 'Context7Schema'],
            'SequentialThinkingSchema' => [SequentialThinkingSchema::class, 'SequentialThinkingSchema'],
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

    /**
     * @dataProvider schemaProvider
     */
    public function testDescriptionsAreNonEmpty(string $class, string $label): void
    {
        $schema = $class::get();

        foreach ($schema as $toolName => $meta) {
            $description = $meta['description'] ?? '';
            $this->assertNotEmpty($description, "$label.$toolName: description must not be empty");
            $this->assertNotEquals('No description available.', $description, "$label.$toolName: description must not be placeholder");
            $this->assertIsString($description, "$label.$toolName: description must be string");
        }
    }

    /**
     * @dataProvider schemaProvider
     */
    public function testInputSchemaStructureIsValid(string $class, string $label): void
    {
        $schema = $class::get();

        foreach ($schema as $toolName => $meta) {
            $types = $meta['types'] ?? [];
            $required = $meta['required'] ?? [];
            $allowed = $meta['allowed'] ?? [];

            // Properties must match types keys
            $propertiesKeys = array_keys($types);
            sort($propertiesKeys);
            $allowedSorted = $allowed;
            sort($allowedSorted);
            $this->assertSame($allowedSorted, $propertiesKeys, "$label.$toolName: types keys must match allowed keys");

            // Required must be subset of allowed
            foreach ($required as $req) {
                $this->assertContains($req, $allowed, "$label.$toolName: required '$req' must be in allowed");
            }
        }
    }

    /**
     * Vector-task specific: verify exposed tools have complete metadata.
     */
    public function testVectorTaskExposedToolsHaveCompleteMetadata(): void
    {
        $schema = VectorTaskSchema::get();
        $exposedTools = ['task_create', 'task_get', 'task_list'];

        foreach ($exposedTools as $toolName) {
            $this->assertArrayHasKey($toolName, $schema, "VectorTaskSchema.$toolName: missing from schema");

            $meta = $schema[$toolName];
            $description = $meta['description'] ?? '';

            $this->assertNotEmpty($description, "VectorTaskSchema.$toolName: description must not be empty");
            $this->assertNotEquals('No description available.', $description, "VectorTaskSchema.$toolName: description must not be placeholder");
            $this->assertArrayHasKey('required', $meta, "VectorTaskSchema.$toolName: missing 'required'");
            $this->assertArrayHasKey('allowed', $meta, "VectorTaskSchema.$toolName: missing 'allowed'");
            $this->assertArrayHasKey('types', $meta, "VectorTaskSchema.$toolName: missing 'types'");
        }
    }

    /**
     * Vector-memory specific: verify exposed tools have complete metadata.
     */
    public function testVectorMemoryExposedToolsHaveCompleteMetadata(): void
    {
        $schema = VectorMemorySchema::get();
        $exposedTools = ['search', 'stats', 'upsert'];

        foreach ($exposedTools as $toolName) {
            $this->assertArrayHasKey($toolName, $schema, "VectorMemorySchema.$toolName: missing from schema");

            $meta = $schema[$toolName];
            $description = $meta['description'] ?? '';

            $this->assertNotEmpty($description, "VectorMemorySchema.$toolName: description must not be empty");
            $this->assertNotEquals('No description available.', $description, "VectorMemorySchema.$toolName: description must not be placeholder");
            $this->assertArrayHasKey('required', $meta, "VectorMemorySchema.$toolName: missing 'required'");
            $this->assertArrayHasKey('allowed', $meta, "VectorMemorySchema.$toolName: missing 'allowed'");
            $this->assertArrayHasKey('types', $meta, "VectorMemorySchema.$toolName: missing 'types'");
        }
    }

    /**
     * Context7 specific: verify exposed tools have complete metadata.
     */
    public function testContext7ExposedToolsHaveCompleteMetadata(): void
    {
        $schema = Context7Schema::get();
        $exposedTools = ['search'];

        foreach ($exposedTools as $toolName) {
            $this->assertArrayHasKey($toolName, $schema, "Context7Schema.$toolName: missing from schema");

            $meta = $schema[$toolName];
            $description = $meta['description'] ?? '';

            $this->assertNotEmpty($description, "Context7Schema.$toolName: description must not be empty");
            $this->assertNotEquals('No description available.', $description, "Context7Schema.$toolName: description must not be placeholder");
            $this->assertArrayHasKey('required', $meta, "Context7Schema.$toolName: missing 'required'");
            $this->assertArrayHasKey('allowed', $meta, "Context7Schema.$toolName: missing 'allowed'");
            $this->assertArrayHasKey('types', $meta, "Context7Schema.$toolName: missing 'types'");
        }
    }

    /**
     * Sequential-thinking specific: verify exposed tools have complete metadata.
     */
    public function testSequentialThinkingExposedToolsHaveCompleteMetadata(): void
    {
        $schema = SequentialThinkingSchema::get();
        $exposedTools = ['think'];

        foreach ($exposedTools as $toolName) {
            $this->assertArrayHasKey($toolName, $schema, "SequentialThinkingSchema.$toolName: missing from schema");

            $meta = $schema[$toolName];
            $description = $meta['description'] ?? '';

            $this->assertNotEmpty($description, "SequentialThinkingSchema.$toolName: description must not be empty");
            $this->assertNotEquals('No description available.', $description, "SequentialThinkingSchema.$toolName: description must not be placeholder");
            $this->assertArrayHasKey('required', $meta, "SequentialThinkingSchema.$toolName: missing 'required'");
            $this->assertArrayHasKey('allowed', $meta, "SequentialThinkingSchema.$toolName: missing 'allowed'");
            $this->assertArrayHasKey('types', $meta, "SequentialThinkingSchema.$toolName: missing 'types'");
        }
    }
}
