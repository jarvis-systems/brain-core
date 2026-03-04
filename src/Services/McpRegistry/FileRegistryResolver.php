<?php

declare(strict_types=1);

namespace BrainCore\Services\McpRegistry;

use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpRegistry\ResolvedRegistry;
use InvalidArgumentException;
use RuntimeException;

final class FileRegistryResolver implements McpRegistryResolver
{
    private const REQUIRED_KEYS = ['version', 'servers'];
    private const SUPPORTED_VERSION = '1.0.0';

    private ?ResolvedRegistry $cached = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $cliPackageDir,
    ) {
    }

    public function resolve(): ResolvedRegistry
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $registryPath = $this->findRegistryFile();

        if ($registryPath === null) {
            throw new RuntimeException('MCP_REGISTRY_MISSING');
        }

        $data = $this->loadAndValidate($registryPath);

        $this->cached = new ResolvedRegistry(
            version: $data['version'],
            servers: $data['servers'],
            resolvedPath: $registryPath,
        );

        return $this->cached;
    }

    private function findRegistryFile(): ?string
    {
        $candidates = [
            $this->projectRoot . '/.brain-config/mcp-registry.json',
            $this->projectRoot . '/.brain/config/mcp-registry.json',
            $this->cliPackageDir . '/mcp-registry.json',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function loadAndValidate(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read registry file: {$path}");
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON in registry file: {$path}", previous: $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException("Registry file must contain a JSON object: {$path}");
        }

        $this->validateStructure($data, $path);
        $this->validateVersion($data['version'], $path);
        $this->validateServers($data['servers'], $path);

        return $data;
    }

    private function validateStructure(array $data, string $path): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required key '{$key}' in registry: {$path}");
            }
        }

        if (!is_array($data['servers'])) {
            throw new InvalidArgumentException("'servers' must be an array in registry: {$path}");
        }
    }

    private function validateVersion(string $version, string $path): void
    {
        if ($version !== self::SUPPORTED_VERSION) {
            throw new InvalidArgumentException(
                "Unsupported registry version '{$version}' (expected: " . self::SUPPORTED_VERSION . "): {$path}"
            );
        }
    }

    private function validateServers(array $servers, string $path): void
    {
        $ids = [];
        foreach ($servers as $index => $server) {
            if (!isset($server['id'], $server['class'], $server['enabled'])) {
                throw new InvalidArgumentException("Server at index {$index} missing required fields in registry: {$path}");
            }

            if (!isset($server['transport'])) {
                throw new InvalidArgumentException("Server at index {$index} missing 'transport' field in registry: {$path}");
            }

            if ($server['transport'] !== 'stdio') {
                throw new InvalidArgumentException("Server at index {$index} has unsupported transport '{$server['transport']}' (only 'stdio' allowed): {$path}");
            }

            if (in_array($server['id'], $ids, true)) {
                throw new InvalidArgumentException("Duplicate server ID '{$server['id']}' in registry: {$path}");
            }
            $ids[] = $server['id'];
        }
    }
}
