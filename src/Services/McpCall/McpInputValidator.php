<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use BrainCore\Services\McpDiscovery\McpDiscoveryService;
use RuntimeException;

/**
 * Lightweight validator for MCP tool input schemas.
 */
final class McpInputValidator
{
    public function __construct(
        private readonly McpDiscoveryService $discoveryService,
    ) {
    }

    /**
     * Validate input against a tool's schema.
     *
     * @throws RuntimeException If validation fails
     */
    public function validate(string $serverId, string $tool, array $input): void
    {
        try {
            $data = $this->discoveryService->describeServer($serverId);
        } catch (RuntimeException $e) {
            return; // Server not found or disabled, executor will handle
        }

        $toolMetadata = null;
        foreach ($data['tools'] ?? [] as $t) {
            if ($t['name'] === $tool) {
                $toolMetadata = $t;
                break;
            }
        }

        if ($toolMetadata === null) {
            return; // Tool not allowed or not found
        }

        $inputSchema = $toolMetadata['input_schema'] ?? [];
        $required = $inputSchema['required'] ?? [];
        $properties = $inputSchema['properties'] ?? [];

        $this->checkRequired($tool, $input, $required);

        // Convert 'properties' array back to 'types' map for checkTypes
        $types = [];
        foreach ($properties as $propName => $propDef) {
            if (isset($propDef['type'])) {
                $types[$propName] = $propDef['type'];
            }
        }

        $this->checkTypes($tool, $input, $types);
    }

    private function checkRequired(string $tool, array $input, array $required): void
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $input)) {
                throw new RuntimeException(
                    "code=MCP_CALL_INVALID_INPUT reason=schema_validation_failed message=\"Input missing a required property.\" hint=\"Run: brain mcp:list ; brain mcp:describe --server=<server>\""
                );
            }
        }
    }

    private function checkTypes(string $tool, array $input, array $types): void
    {
        foreach ($input as $key => $value) {
            if (!isset($types[$key])) {
                continue; // Flexible allowlist by default for now
            }

            $expected = $types[$key];
            $actual = gettype($value);
            if ($actual === 'integer')
                $actual = 'integer';
            if ($actual === 'double')
                $actual = 'float';

            if ($actual !== $expected) {
                // Allow float for integer keys if value is whole number
                if ($expected === 'integer' && $actual === 'float' && floor($value) === $value) {
                    continue;
                }

                throw new RuntimeException(
                    "code=MCP_CALL_INVALID_INPUT reason=schema_validation_failed message=\"Property type mismatch.\" hint=\"Run: brain mcp:list ; brain mcp:describe --server=<server>\""
                );
            }
        }
    }

}

