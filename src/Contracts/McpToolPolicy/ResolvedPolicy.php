<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpToolPolicy;

use BrainCore\Support\StableJsonTrait;

final readonly class ResolvedPolicy
{
    use StableJsonTrait;

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

    /**
     * Export to a stable array representation for MCP protocol responses.
     *
     * @return array{enabled: bool, kill_switch_env: string, schema_version: string, allowed: string[], never: string[], clients: array, resolved_path: string|null}
     */
    public function toStableArray(): array
    {
        return $this->stabilizeArray([
            'allowed' => $this->allowed,
            'clients' => $this->clients,
            'enabled' => $this->enabled,
            'kill_switch_env' => $this->killSwitchEnv,
            'never' => $this->never,
            'resolved_path' => $this->resolvedPath,
            'schema_version' => $this->version,
        ]);
    }
}
