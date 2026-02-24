<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpToolPolicy;

final readonly class ResolvedPolicy
{
    public function __construct(
        public bool $enabled,
        public string $version,
        public string $killSwitchEnv,
        public array $allowed,
        public array $never,
        public array $clients,
        public ?string $resolvedPath = null,
    ) {}

    public static function disabled(string $killSwitchEnv = 'BRAIN_DISABLE_MCP'): self
    {
        return new self(
            enabled: false,
            version: '1.0.0',
            killSwitchEnv: $killSwitchEnv,
            allowed: [],
            never: [],
            clients: [],
            resolvedPath: null,
        );
    }
}
