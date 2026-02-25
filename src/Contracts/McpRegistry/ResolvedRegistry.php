<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpRegistry;

use BrainCore\Support\StableJsonTrait;

final readonly class ResolvedRegistry
{
    use StableJsonTrait;

    /**
     * @param array<int, array{id: string, class: string, enabled: bool}> $servers
     */
    public function __construct(
        public string $version,
        public array $servers,
        public ?string $resolvedPath = null,
    ) {}

    /**
     * Export to a stable array representation.
     */
    public function toStableArray(): array
    {
        return $this->stabilizeArray([
            'version' => $this->version,
            'servers' => $this->servers,
            'resolved_path' => $this->resolvedPath,
        ]);
    }
}
