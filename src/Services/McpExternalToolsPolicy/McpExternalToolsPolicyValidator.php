<?php

declare(strict_types=1);

namespace BrainCore\Services\McpExternalToolsPolicy;

use RuntimeException;

/**
 * Validates the MCP External Tools Policy structure and semantics.
 */
final class McpExternalToolsPolicyValidator
{
    public const BUILTIN_SERVERS = ['brain-tools'];

    /**
     * @param array $policyData The raw decoded JSON policy data
     * @param array|null $registryData Optional raw decoded JSON registry data
     * 
     * @throws RuntimeException If validation fails
     */
    public function validate(array $policyData, ?array $registryData = null): void
    {
        if (!isset($policyData['servers']) || !is_array($policyData['servers'])) {
            return; // Covered by FileExternalToolsPolicyResolver basic structure checks
        }

        $servers = $policyData['servers'];

        // 1. Stable Sorting: server keys must be sorted alphabetically
        $serverKeys = array_keys($servers);
        $sortedServerKeys = $serverKeys;
        sort($sortedServerKeys, SORT_STRING);

        if ($serverKeys !== $sortedServerKeys) {
            throw new RuntimeException("code=MCP_POLICY_INVALID reason=servers_not_sorted message=\"Policy 'servers' keys are not alphabetically sorted.\"");
        }

        foreach ($servers as $serverId => $serverCfg) {
            $toolsAllowed = $serverCfg['tools_allowed'] ?? [];
            if (!is_array($toolsAllowed)) {
                continue; // Covered by structure check
            }

            // 2. Tools sorting and uniqueness
            $sortedTools = $toolsAllowed;
            sort($sortedTools, SORT_STRING);

            if ($toolsAllowed !== $sortedTools) {
                throw new RuntimeException("code=MCP_POLICY_INVALID reason=tools_not_sorted message=\"Tools for server '{$serverId}' in policy are not alphabetically sorted.\"");
            }

            $uniqueTools = array_unique($toolsAllowed);
            if (count($uniqueTools) !== count($toolsAllowed)) {
                throw new RuntimeException("code=MCP_POLICY_INVALID reason=duplicate_tools message=\"Tools array for server '{$serverId}' contains duplicates.\"");
            }

            // 3. Enabled status checking against registry (skip builtins)
            if (($serverCfg['enabled'] ?? false) && $registryData !== null && !in_array($serverId, self::BUILTIN_SERVERS, true)) {
                $foundInRegistry = false;
                $registryServers = $registryData['servers'] ?? [];
                foreach ($registryServers as $regServer) {
                    if (is_array($regServer) && isset($regServer['id']) && $regServer['id'] === $serverId) {
                        $foundInRegistry = true;
                        break;
                    }
                }

                if (!$foundInRegistry) {
                    throw new RuntimeException("code=MCP_POLICY_INVALID reason=server_not_in_registry message=\"Server '{$serverId}' is enabled in policy but does not exist in registry.\"");
                }
            }
        }
    }
}
