<?php

declare(strict_types=1);

namespace BrainCore\Mcp;

use InvalidArgumentException;

final class McpSchemaValidator
{
    public static function validate(
        string $method,
        array $args,
        array $schema,
        string $mode = 'standard'
    ): void {
        if (!isset($schema[$method])) {
            if (($mode === 'strict' || $mode === 'paranoid') && !empty($schema)) {
                throw new InvalidArgumentException(
                    "MCP schema validation failed: unknown method '$method' in $mode mode"
                );
            }
            return;
        }

        $methodSchema = $schema[$method];

        foreach ($methodSchema['required'] ?? [] as $key) {
            if (!array_key_exists($key, $args)) {
                throw new InvalidArgumentException(
                    "MCP schema validation failed: missing required key '$key' for method '$method'"
                );
            }
        }

        if ($mode === 'strict' || $mode === 'paranoid') {
            $extra = array_diff(array_keys($args), $methodSchema['allowed'] ?? []);
            if (!empty($extra)) {
                throw new InvalidArgumentException(
                    "MCP schema validation failed: unknown keys [" . implode(', ', $extra) . "] for method '$method' in $mode mode"
                );
            }
        }

        if ($mode === 'paranoid' && isset($methodSchema['types'])) {
            foreach ($args as $key => $value) {
                if (isset($methodSchema['types'][$key])) {
                    self::validateType($key, $value, $methodSchema['types'][$key], $method);
                }
            }
        }
    }

    private static function validateType(string $key, mixed $value, string $expectedType, string $method): void
    {
        $actualType = gettype($value);
        $typeMap = [
            'string' => 'string',
            'integer' => 'integer',
            'int' => 'integer',
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'array' => 'array',
            'float' => 'double',
            'double' => 'double',
        ];

        $normalizedExpected = $typeMap[$expectedType] ?? $expectedType;

        if ($normalizedExpected === 'array' && !is_array($value)) {
            throw new InvalidArgumentException(
                "MCP schema validation failed: key '$key' must be array, got $actualType for method '$method' in paranoid mode"
            );
        }

        if ($normalizedExpected !== 'array' && $actualType !== $normalizedExpected) {
            throw new InvalidArgumentException(
                "MCP schema validation failed: key '$key' must be $expectedType, got $actualType for method '$method' in paranoid mode"
            );
        }
    }
}
