<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class BrainToolsSchema
{
    public static function get(): array
    {
        return [
            'docs_search' => [
                'description' => 'Search and analyze this project\'s documentation (.docs/) and return structured JSON results. Supports rich filters and metadata extraction. Deterministic output; stderr is always empty.',
                'required' => ['query'],
                'allowed' => ['query', 'limit', 'headers'],
                'types' => [
                    'query' => 'string',
                    'limit' => 'integer',
                    'headers' => 'array',
                ],
            ],
            'diagnose' => [
                'description' => 'Return structured JSON diagnostics about the Brain environment and runtime configuration. Deterministic output; stderr is always empty.',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'list_masters' => [
                'description' => 'List available master sub-agents for the current agent context. Returns JSON. Deterministic output; stderr is always empty.',
                'required' => [],
                'allowed' => ['agent'],
                'types' => [
                    'agent' => 'string',
                ],
            ],
        ];
    }
}
