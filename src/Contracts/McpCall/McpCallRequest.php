<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpCall;

/**
 * MCP Call Request DTO
 */
final class McpCallRequest
{
    /**
     * @param string $serverId The ID of the server in the registry
     * @param string $tool     The name of the tool to call
     * @param array $input     Tool arguments
     */
    public function __construct(
        public readonly string $serverId,
        public readonly string $tool,
        public readonly array $input,
    ) {}
}
