<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpExternalToolsPolicy;

/**
 * Resolved external tool policy state.
 */
final class ResolvedExternalToolsPolicy
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $version,
        public readonly string $killSwitchEnv,
        public readonly array $servers,
        public readonly ?string $resolvedPath = null,
    ) {}

    public static function disabled(string $killSwitchEnv): self
    {
        return new self(false, '1.0.0', $killSwitchEnv, []);
    }
}
