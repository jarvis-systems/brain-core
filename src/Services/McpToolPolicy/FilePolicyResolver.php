<?php

declare(strict_types=1);

namespace BrainCore\Services\McpToolPolicy;

use BrainCore\Contracts\McpToolPolicy\McpToolPolicyResolver;
use BrainCore\Contracts\McpToolPolicy\ResolvedPolicy;
use InvalidArgumentException;
use RuntimeException;

final class FilePolicyResolver implements McpToolPolicyResolver
{
    private const REQUIRED_KEYS = ['version', 'allowed', 'never'];
    private const SUPPORTED_VERSION = '1.0.0';
    private const KILL_SWITCH_ENV = 'BRAIN_DISABLE_MCP';

    private ?ResolvedPolicy $cached = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $cliPackageDir,
    ) {}

    public function resolve(): ResolvedPolicy
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if ($this->isKillSwitchActive()) {
            $this->cached = ResolvedPolicy::disabled(self::KILL_SWITCH_ENV);

            return $this->cached;
        }

        $policyPath = $this->findPolicyFile();

        if ($policyPath === null) {
            throw new RuntimeException('No MCP tool policy file found');
        }

        $data = $this->loadAndValidate($policyPath);

        $this->cached = new ResolvedPolicy(
            enabled: true,
            version: $data['version'],
            killSwitchEnv: $data['kill_switch_env'] ?? self::KILL_SWITCH_ENV,
            allowed: $data['allowed'],
            never: $data['never'],
            clients: $data['clients'] ?? [],
            resolvedPath: $policyPath,
        );

        return $this->cached;
    }

    public function isAllowed(string $command): bool
    {
        $policy = $this->resolve();

        if (! $policy->enabled) {
            return false;
        }

        return in_array($command, $policy->allowed, true);
    }

    public function isNever(string $command): bool
    {
        $policy = $this->resolve();

        foreach ($policy->never as $neverPattern) {
            if ($this->matchesPattern($command, $neverPattern)) {
                return true;
            }
        }

        return false;
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
            $this->projectRoot . '/.brain-config/mcp-tools.allowlist.json',
            $this->projectRoot . '/.brain/config/mcp-tools.allowlist.json',
            $this->cliPackageDir . '/mcp-tools.allowlist.json',
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
            throw new RuntimeException("Failed to read policy file: {$path}");
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON in policy file: {$path}", previous: $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException("Policy file must contain a JSON object: {$path}");
        }

        $this->validateStructure($data, $path);
        $this->validateVersion($data['version'], $path);
        $this->validateNoOverlap($data['allowed'], $data['never'], $path);

        return $data;
    }

    private function validateStructure(array $data, string $path): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required key '{$key}' in policy: {$path}");
            }
        }

        if (! is_array($data['allowed'])) {
            throw new InvalidArgumentException("'allowed' must be an array in policy: {$path}");
        }

        if (! is_array($data['never'])) {
            throw new InvalidArgumentException("'never' must be an array in policy: {$path}");
        }
    }

    private function validateVersion(string $version, string $path): void
    {
        if ($version !== self::SUPPORTED_VERSION) {
            throw new InvalidArgumentException(
                "Unsupported policy version '{$version}' (expected: " . self::SUPPORTED_VERSION . "): {$path}"
            );
        }
    }

    private function validateNoOverlap(array $allowed, array $never, string $path): void
    {
        $overlap = array_intersect($allowed, $never);

        if (! empty($overlap)) {
            throw new InvalidArgumentException(
                "Policy has overlap between allowed and never: " . implode(', ', $overlap) . " in {$path}"
            );
        }
    }

    private function matchesPattern(string $command, string $pattern): bool
    {
        if ($command === $pattern) {
            return true;
        }

        if (str_ends_with($pattern, ':*')) {
            $prefix = substr($pattern, 0, -1);

            return str_starts_with($command, $prefix);
        }

        return false;
    }
}
