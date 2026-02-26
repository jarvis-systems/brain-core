<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Locks consistency between mcp:describe CLI output and preflight validation.
 *
 * This test ensures mcp:describe is the single source of truth for schemas-as-tools metadata.
 * Uses CLI subprocess to get actual describe output, ensuring end-to-end contract.
 */
class McpDescribeVsPreflightContractTest extends TestCase
{
    private const ENABLED_SERVERS = ['context7', 'sequential-thinking', 'vector-memory', 'vector-task'];

    private static array $describeCache = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Cache describe output for all servers
        foreach (self::ENABLED_SERVERS as $serverId) {
            $output = self::runCli("mcp:describe --server=$serverId");
            self::$describeCache[$serverId] = json_decode($output, true);
        }
    }

    private static function runCli(string $command): string
    {
        $root = dirname(__DIR__, 2);
        $cmd = "php $root/cli/bin/brain $command 2>/dev/null";
        return shell_exec($cmd) ?: '';
    }

    public function testAllEnabledServersHaveDescribeOutput(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];
            $this->assertNotNull($data, "$serverId: describe output should be valid JSON");
            $this->assertTrue($data['enabled'] ?? false, "$serverId should be enabled");
            $this->assertArrayHasKey('tools', $data['data'], "$serverId should have tools array");
            $this->assertNotEmpty($data['data']['tools'], "$serverId should have at least one tool");
        }
    }

    public function testToolNamesAreUnique(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];
            $names = array_map(fn($t) => $t['name'], $data['data']['tools']);
            $uniqueNames = array_unique($names);
            $this->assertSame(count($names), count($uniqueNames), "$serverId: tool names must be unique");
        }
    }

    public function testInputSchemaHasCanonicalShape(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];

            foreach ($data['data']['tools'] as $tool) {
                $schema = $tool['input_schema'];
                $toolName = "$serverId.{$tool['name']}";

                $this->assertArrayHasKey('type', $schema, "$toolName: input_schema must have type");
                $this->assertSame('object', $schema['type'], "$toolName: input_schema.type must be 'object'");
                $this->assertArrayHasKey('properties', $schema, "$toolName: input_schema must have properties");
                $this->assertArrayHasKey('required', $schema, "$toolName: input_schema must have required");
            }
        }
    }

    public function testRequiredIsSubsetOfProperties(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];

            foreach ($data['data']['tools'] as $tool) {
                $schema = $tool['input_schema'];
                $toolName = "$serverId.{$tool['name']}";
                $propertiesKeys = array_keys($schema['properties']);

                foreach ($schema['required'] as $req) {
                    $this->assertContains($req, $propertiesKeys, "$toolName: required '$req' must exist in properties");
                }
            }
        }
    }

    public function testPropertiesKeysAreSorted(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];

            foreach ($data['data']['tools'] as $tool) {
                $schema = $tool['input_schema'];
                $toolName = "$serverId.{$tool['name']}";
                $keys = array_keys($schema['properties']);
                $sorted = $keys;
                sort($sorted);
                $this->assertSame($sorted, $keys, "$toolName: properties keys must be sorted ASC");
            }
        }
    }

    public function testToolsAreSortedByName(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];
            $names = array_map(fn($t) => $t['name'], $data['data']['tools']);
            $sorted = $names;
            sort($sorted);
            $this->assertSame($sorted, $names, "$serverId: tools must be sorted by name ASC");
        }
    }

    public function testDescriptionsAreNonEmpty(): void
    {
        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];

            foreach ($data['data']['tools'] as $tool) {
                $toolName = "$serverId.{$tool['name']}";
                $this->assertNotEmpty($tool['description'], "$toolName: description must not be empty");
                $this->assertNotEquals('No description available.', $tool['description'], "$toolName: description must not be placeholder");
            }
        }
    }

    public function testDescribeStderrIsEmpty(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (self::ENABLED_SERVERS as $serverId) {
            $cmd = "php $root/cli/bin/brain mcp:describe --server=$serverId 2>&1 1>/dev/null";
            $stderr = shell_exec($cmd) ?: '';
            $this->assertSame('', $stderr, "$serverId: describe should have empty stderr");
        }
    }

    public function testPreflightMissingRequiredHasGenericMessage(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];

            foreach ($data['data']['tools'] as $tool) {
                if (empty($tool['input_schema']['required'])) {
                    continue;
                }

                $toolName = $tool['name'];
                $testName = "$serverId.$toolName: missing required should have generic error";

                // Pass empty JSON to trigger missing required
                $cmd = "php $root/cli/bin/brain mcp:call --server=$serverId --tool=$toolName --input='{}' 2>/dev/null";
                $output = shell_exec($cmd) ?: '';
                $json = json_decode($output, true);

                $this->assertFalse($json['ok'] ?? true, "$testName - should fail");
                $this->assertArrayHasKey('error', $json, "$testName - should have error");
                $this->assertEquals('MCP_CALL_INVALID_INPUT', $json['error']['code'] ?? '', "$testName - should have MCP_CALL_INVALID_INPUT");

                $reason = $json['error']['reason'] ?? '';
                $message = $json['error']['message'] ?? '';

                // Should be either schema_validation_failed or blocked by policy
                if ($reason === 'schema_validation_failed') {
                    $this->assertStringContainsString('required property', $message, "$testName - should mention required property");
                    $firstRequired = $tool['input_schema']['required'][0];
                    $this->assertStringNotContainsString($firstRequired, $message, "$testName - should NOT leak property name");
                }
            }
        }
    }

    public function testPreflightTypeMismatchHasGenericMessage(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (self::ENABLED_SERVERS as $serverId) {
            $data = self::$describeCache[$serverId];

            foreach ($data['data']['tools'] as $tool) {
                $properties = $tool['input_schema']['properties'];
                $required = $tool['input_schema']['required'];
                $toolName = $tool['name'];

                // Find a property with integer or boolean type we can mismatch
                $propToMismatch = null;
                $propType = null;
                foreach ($properties as $name => $def) {
                    $type = $def['type'] ?? '';
                    if (in_array($type, ['integer', 'boolean'])) {
                        $propToMismatch = $name;
                        $propType = $type;
                        break;
                    }
                }

                if ($propToMismatch === null) {
                    continue;
                }

                // Build input with all required fields + the mismatched field
                $input = [];
                foreach ($required as $req) {
                    $reqType = $properties[$req]['type'] ?? 'string';
                    $input[$req] = $this->getValidValueForType($reqType);
                }

                // Add the field with wrong type
                $input[$propToMismatch] = 'wrong_type_string_for_' . $propType;

                $testName = "$serverId.$toolName: type mismatch should have generic error";

                $inputJson = json_encode($input);
                $cmd = "php $root/cli/bin/brain mcp:call --server=$serverId --tool=$toolName --input='$inputJson' 2>/dev/null";
                $output = shell_exec($cmd) ?: '';
                $json = json_decode($output, true);

                // If the call succeeded, this tool doesn't validate this property - skip
                if ($json['ok'] ?? false) {
                    continue;
                }

                $reason = $json['error']['reason'] ?? '';
                if ($reason === 'schema_validation_failed') {
                    $message = strtolower($json['error']['message'] ?? '');
                    $this->assertStringContainsString('type mismatch', $message, "$testName - should mention type mismatch");
                }
            }
        }
    }

    private function getValidValueForType(string $type): mixed
    {
        return match ($type) {
            'integer' => 1,
            'float' => 1.0,
            'boolean' => true,
            'array' => [],
            default => 'test',
        };
    }
}
