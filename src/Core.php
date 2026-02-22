<?php

declare(strict_types=1);

namespace BrainCore;

use Bfg\Dto\Dto;

class Core
{
    /**
     * Env key prefixes that may influence compilation.
     * Process env vars outside these namespaces are treated as absent
     * by the strict static accessor Core::env().
     */
    public const ALLOWED_ENV_PREFIXES = [
        'BRAIN_',
    ];

    /**
     * Non-prefixed env keys explicitly allowed by Core::env().
     */
    public const ALLOWED_ENV_KEYS = [
        'DEBUG',
    ];

    protected string|null $versionCache = null;

    protected array $variables = [];

    protected Dto|null $currentCompileDto = null;

    public function basePath(string|array $path = '', bool $relative = false): string
    {
        if (! $relative) {
            $cwd = getcwd();

            if ($cwd === false) {
                throw new \RuntimeException('Unable to get current working directory.');
            }
        } else {
            $cwd = '';
        }

        $path = is_array($path) ? implode(DS, array_filter(array_map(fn (string $p) => trim($p, DS), array_filter($path)))) : $path;

        return $cwd . ($relative ? '' : DS) . ltrim($path, DS);
    }

    public function version(): string|null
    {
        if ($this->versionCache !== null) {
            return $this->versionCache;
        }

        $composerPath = dirname(__DIR__) . DS . 'composer.json';
        if (is_file($composerPath)) {
            $json = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($json) && isset($json['version']) && is_string($json['version'])) {
                return $this->versionCache = $json['version'];
            }
        }

        return $this->versionCache = null;
    }

    public function setVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    public function getVariable(string $name, mixed $default = null): mixed
    {
        return $this->variables[$name]
            ?? ($this->variables[strtoupper($name)] ?? value($default));
    }

    public function mergeVariables(array ...$arrays): void
    {
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                $this->variables[$key] = $value;
            }
        }
    }

    public function setCurrentCompileDto(Dto|null $dto): void
    {
        $this->currentCompileDto = $dto;
    }

    public function getCurrentCompileDto(): Dto|null
    {
        return $this->currentCompileDto;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function allVariables(string|null $findName = null): array
    {
        $vars = [];
        foreach ($this->variables as $key => $value) {
            if ($findName !== null && ! str_starts_with($key, $findName)) {
                continue;
            }
            $vars[$key] = $value;
        }
        return $vars;
    }

    public function isDebug(): bool
    {
        return !! $this->getEnv('BRAIN_CORE_DEBUG')
            || !! $this->getEnv('DEBUG');
    }

    /**
     * Check if an env key is in the compile-time allowlist.
     *
     * Matches ALLOWED_ENV_PREFIXES (prefix) or ALLOWED_ENV_KEYS (exact).
     */
    public static function isAllowedEnvKey(string $key): bool
    {
        $key = strtoupper($key);

        if (in_array($key, self::ALLOWED_ENV_KEYS, true)) {
            return true;
        }

        foreach (self::ALLOWED_ENV_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strict env accessor with allowlist gate.
     *
     * Only reads env keys matching ALLOWED_ENV_PREFIXES or
     * listed in ALLOWED_ENV_KEYS. Unknown keys return $default.
     * Use for direct env reads outside the var() resolution chain.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $key = strtoupper($key);

        if (! self::isAllowedEnvKey($key)) {
            return $default;
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return self::castEnvValue($value);
    }

    public function hasEnv(string $name): bool
    {
        return getenv(strtoupper($name)) !== false;
    }

    public function allEnv(string|null $findName = null): array
    {
        $envs = [];
        foreach (getenv() as $key => $value) {
            if (! self::isAllowedEnvKey($key)) {
                continue;
            }
            if ($findName !== null && ! str_starts_with($key, $findName)) {
                continue;
            }
            $envs[$key] = $this->getEnv($key);
        }
        return $envs;
    }

    public function getEnv(string $name): mixed
    {
        $name = strtoupper($name);
        $value = getenv($name);
        if ($value === false) {
            return null;
        }

        return self::castEnvValue($value);
    }

    /**
     * Cast raw env string to typed PHP value.
     */
    private static function castEnvValue(string $value): mixed
    {
        if ($value === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }
        if (
            (str_starts_with($value, '[') && str_ends_with($value, ']'))
            || (str_starts_with($value, '{') && str_ends_with($value, '}'))
        ) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $value;
    }
}
