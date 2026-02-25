<?php

declare(strict_types=1);

namespace BrainCore\Services\McpDiscovery;

use BrainCore\Contracts\McpRegistry\McpRegistryResolver;
use BrainCore\Contracts\McpExternalToolsPolicy\McpExternalToolsPolicyResolver;
use BrainCore\Mcp\Traits\McpSchemaTrait;
use RuntimeException;

/**
 * Service for MCP discovery, combining registry and policy.
 */
final class McpDiscoveryService
{
    public function __construct(
        private readonly McpRegistryResolver $registryResolver,
        private readonly McpExternalToolsPolicyResolver $policyResolver,
    ) {}

    /**
     * List all servers and their allowed tools.
     */
    public function listServers(): array
    {
        if (! $this->policyResolver->isEnabled()) {
            return [];
        }

        $registry = $this->registryResolver->resolve();
        $policy = $this->policyResolver->resolve();

        $servers = [];
        $toolsAllowedTotal = 0;

        foreach ($registry->servers as $server) {
            $serverId = $server['id'];
            $isEnabled = $server['enabled'] && ($policy->servers[$serverId]['enabled'] ?? false);
            
            $allowedTools = $policy->servers[$serverId]['tools_allowed'] ?? [];
            sort($allowedTools);

            $servers[] = [
                'id' => $serverId,
                'enabled' => $isEnabled,
                'allowed_tools' => $allowedTools,
            ];

            if ($isEnabled) {
                $toolsAllowedTotal += count($allowedTools);
            }
        }

        // Deterministic sort by ID
        usort($servers, fn($a, $b) => strcmp($a['id'], $b['id']));

        return [
            'servers' => $servers,
            'summary' => [
                'servers_total' => count($registry->servers),
                'servers_enabled' => count(array_filter($servers, fn($s) => $s['enabled'])),
                'tools_allowed_total' => $toolsAllowedTotal,
            ]
        ];
    }

    /**
     * Describe a single server and its tools (filtered by policy).
     */
    public function describeServer(string $serverId): array
    {
        if (! $this->policyResolver->isEnabled()) {
            throw new RuntimeException("MCP operations are disabled via kill-switch.");
        }

        $registry = $this->registryResolver->resolve();
        $serverEntry = null;
        foreach ($registry->servers as $server) {
            if ($server['id'] === $serverId) {
                $serverEntry = $server;
                break;
            }
        }

        if ($serverEntry === null) {
            throw new RuntimeException("code=MCP_SERVER_NOT_FOUND reason=registry_missing_id message='Server {$serverId} not found in registry.'");
        }

        $policy = $this->policyResolver->resolve();
        $serverPolicy = $policy->servers[$serverId] ?? null;

        if (! ($serverEntry['enabled']) || ! ($serverPolicy['enabled'] ?? false)) {
             throw new RuntimeException("code=MCP_SERVER_DISABLED reason=server_not_enabled message='Server {$serverId} is disabled.'");
        }

        $allowedTools = $serverPolicy['tools_allowed'] ?? [];
        
        $class = $serverEntry['class'];
        $tools = [];

        if (class_exists($class)) {
            // Check if class uses McpSchemaTrait or has schema() method
            if (method_exists($class, 'schema')) {
                $fullSchema = $class::schema();
                foreach ($allowedTools as $toolName) {
                    if (isset($fullSchema[$toolName])) {
                        $toolMetadata = $fullSchema[$toolName];
                        $tools[] = [
                            'name' => $toolName,
                            'description' => $toolMetadata['description'] ?? "No description available.",
                            'input_schema' => [
                                'type' => 'object',
                                'properties' => $this->formatProperties($toolMetadata['types'] ?? []),
                                'required' => $toolMetadata['required'] ?? [],
                            ]
                        ];
                    } else {
                        // Tool allowed in policy but missing in schema
                        $tools[] = [
                            'name' => $toolName,
                            'description' => "Tool found in policy but schema is missing in class.",
                            'input_schema' => ['type' => 'object']
                        ];
                    }
                }
            } else {
                // Class exists but no static schema method
                foreach ($allowedTools as $toolName) {
                    $tools[] = [
                        'name' => $toolName,
                        'description' => "Tool found in policy but server class does not provide static metadata.",
                        'input_schema' => ['type' => 'object']
                    ];
                }
            }
        } else {
             throw new RuntimeException("code=MCP_CLASS_NOT_FOUND reason=autoload_failure message='Class {$class} for server {$serverId} not found.'");
        }

        usort($tools, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'enabled' => true,
            'server' => $serverId,
            'tools' => $tools,
        ];
    }

    private function formatProperties(array $types): array
    {
        $properties = [];
        foreach ($types as $name => $type) {
            $properties[$name] = ['type' => $type];
        }
        ksort($properties);
        return $properties;
    }
}
