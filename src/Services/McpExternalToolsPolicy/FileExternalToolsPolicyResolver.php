<?php

declare(strict_types=1);

namespace BrainCore\Services\McpExternalToolsPolicy;

use BrainCore\Contracts\McpExternalToolsPolicy\McpExternalToolsPolicyResolver;
use BrainCore\Contracts\McpExternalToolsPolicy\ResolvedExternalToolsPolicy;
use InvalidArgumentException;
use RuntimeException;

final class FileExternalToolsPolicyResolver implements McpExternalToolsPolicyResolver
{
    private const REQUIRED_KEYS = ['schema_version', 'servers'];
    private const SUPPORTED_VERSION = '1.0.0';
    private const KILL_SWITCH_ENV = 'BRAIN_DISABLE_MCP';

    private ?ResolvedExternalToolsPolicy $cached = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $cliPackageDir,
    ) {}

    public function resolve(): ResolvedExternalToolsPolicy
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if ($this->isKillSwitchActive()) {
            $this->cached = ResolvedExternalToolsPolicy::disabled(self::KILL_SWITCH_ENV);
            return $this->cached;
        }

        $policyPath = $this->findPolicyFile();

        if ($policyPath === null) {
            throw new RuntimeException('MCP_EXTERNAL_TOOLS_POLICY_MISSING');
        }

        $data = $this->loadAndValidate($policyPath);

        $this->cached = new ResolvedExternalToolsPolicy(
            enabled: true,
            version: $data['schema_version'],
            killSwitchEnv: $data['kill_switch_env'] ?? self::KILL_SWITCH_ENV,
            servers: $data['servers'],
            resolvedPath: $policyPath,
        );

        return $this->cached;
    }

    public function isAllowed(string $serverId, string $tool): bool
    {
        $policy = $this->resolve();

        if (! $policy->enabled) {
            return false;
        }

        if (! isset($policy->servers[$serverId])) {
            return false;
        }

        $server = $policy->servers[$serverId];

        if (! ($server['enabled'] ?? false)) {
            return false;
        }

        $allowedTools = $server['tools_allowed'] ?? [];

        return in_array($tool, $allowedTools, true);
    }

    public function isEnabled(): bool
    {
        return $this->resolve()->enabled;
    }

    private function isKillSwitchActive(): bool
    {
        $value = getenv(self::KILL_SWITCH_ENV);

        if ($value === false) {
            return false;
        }

        return in_array(strtolower((string) $value), ['true', '1', 'yes'], true);
    }

    private function findPolicyFile(): ?string
    {
        $candidates = [
            $this->projectRoot . '/.brain-config/mcp-external-tools.allowlist.json',
            $this->projectRoot . '/.brain/config/mcp-external-tools.allowlist.json',
            $this->cliPackageDir . '/mcp-external-tools.allowlist.json',
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
            throw new RuntimeException("Failed to read external tools policy file: {$path}");
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON in external tools policy file: {$path}", previous: $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException("External tools policy file must contain a JSON object: {$path}");
        }

        $this->validateStructure($data, $path);
        $this->validateVersion($data['schema_version'], $path);
        $this->validateServers($data['servers'], $path);

        return $data;
    }

    private function validateStructure(array $data, string $path): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required key '{$key}' in policy: {$path}");
            }
        }

        if (! is_array($data['servers'])) {
            throw new InvalidArgumentException("'servers' must be an array/object in policy: {$path}");
        }
    }

    private function validateVersion(string $version, string $path): void
    {
        if ($version !== self::SUPPORTED_VERSION) {
            throw new InvalidArgumentException(
                "Unsupported external tools policy version '{$version}' (expected: " . self::SUPPORTED_VERSION . "): {$path}"
            );
        }
    }

    private function validateServers(array $servers, string $path): void
    {
        foreach ($servers as $id => $server) {
            if (! is_array($server)) {
                throw new InvalidArgumentException("Server '{$id}' must be an object in policy: {$path}");
            }

            if (! isset($server['enabled'])) {
                throw new InvalidArgumentException("Server '{$id}' missing 'enabled' field in policy: {$path}");
            }

            if (! isset($server['tools_allowed'])) {
                throw new InvalidArgumentException("Server '{$id}' missing 'tools_allowed' field in policy: {$path}");
            }

            if (! is_array($server['tools_allowed'])) {
                throw new InvalidArgumentException("'tools_allowed' for '{$id}' must be an array in policy: {$path}");
            }
        }
    }
}
