<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpExternalToolsPolicy;

/**
 * Resolver for external MCP tool policies (mcp:call gating).
 */
interface McpExternalToolsPolicyResolver
{
    /**
     * Resolve the external tools policy.
     */
    public function resolve(): ResolvedExternalToolsPolicy;

    /**
     * Check if a tool is allowed for a given server.
     */
    public function isAllowed(string $serverId, string $tool): bool;

    /**
     * Check if external MCP calls are enabled globally.
     */
    public function isEnabled(): bool;
}
