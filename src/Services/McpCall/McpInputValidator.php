<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use RuntimeException;

/**
 * Lightweight validator for MCP tool input schemas.
 */
final class McpInputValidator
{
    public function __construct(
        private readonly McpRegistryResolver $registryResolver,
        private readonly string $projectRoot,
    ) {}

    /**
     * Validate input against a tool's schema.
     *
     * @throws RuntimeException If validation fails
     */
    public function validate(string $serverId, string $tool, array $input): void
    {
        $registry = $this->registryResolver->resolve();
        $serverEntry = null;
        foreach ($registry->servers as $server) {
            if ($server['id'] === $serverId) {
                $serverEntry = $server;
                break;
            }
        }

        if ($serverEntry === null) {
            return; // Executor will handle server not found
        }

        $class = $serverEntry['class'];
        if (! class_exists($class)) {
            $this->ensureRootAutoloader();
        }

        if (! class_exists($class) || ! method_exists($class, 'schema')) {
            return; // No schema to validate against
        }

        $fullSchema = $class::schema();
        if (! isset($fullSchema[$tool])) {
            return; // Tool not in schema
        }

        $toolSchema = $fullSchema[$tool];
        $this->checkRequired($tool, $input, $toolSchema['required'] ?? []);
        $this->checkTypes($tool, $input, $toolSchema['types'] ?? []);
    }

    private function checkRequired(string $tool, array $input, array $required): void
    {
        foreach ($required as $key) {
            if (! array_key_exists($key, $input)) {
                throw new RuntimeException(
                    "code=MCP_CALL_INVALID_INPUT reason=schema_validation_failed message=\"Missing required property: {$key}\" hint=\"Run: brain mcp:describe --server=<id> and adjust --input\""
                );
            }
        }
    }

    private function checkTypes(string $tool, array $input, array $types): void
    {
        foreach ($input as $key => $value) {
            if (! isset($types[$key])) {
                continue; // Flexible allowlist by default for now
            }

            $expected = $types[$key];
            $actual = gettype($value);
            if ($actual === 'integer') $actual = 'integer';
            if ($actual === 'double') $actual = 'float';

            if ($actual !== $expected) {
                // Allow float for integer keys if value is whole number
                if ($expected === 'integer' && $actual === 'float' && floor($value) === $value) {
                    continue;
                }
                
                throw new RuntimeException(
                    "code=MCP_CALL_INVALID_INPUT reason=schema_validation_failed message=\"Property '{$key}' must be {$expected}, got {$actual}.\" hint=\"Run: brain mcp:describe --server=<id> and adjust --input\""
                );
            }
        }
    }

    private function ensureRootAutoloader(): void
    {
        $projectRoot = $this->projectRoot;
        $rootAutoloader = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($rootAutoloader)) {
            require_once $rootAutoloader;
        }
    }
}
