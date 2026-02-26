<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class VectorMemorySchema
{
    public static function get(): array
    {
        return [
            'clear_old_memories' => [
                'description' => 'Remove old memories by age and retention limit',
                'required' => [],
                'allowed' => ['days_old', 'max_to_keep'],
                'types' => [
                    'days_old' => 'integer',
                    'max_to_keep' => 'integer',
                ],
            ],
            'cookbook' => [
                'description' => 'Retrieve cookbook cases for memory operations',
                'required' => [],
                'allowed' => ['case_category', 'cognitive', 'include', 'level', 'limit', 'offset', 'priority', 'query', 'strict'],
                'types' => [
                    'case_category' => 'string',
                    'cognitive' => 'string',
                    'include' => 'string',
                    'level' => 'integer',
                    'limit' => 'integer',
                    'offset' => 'integer',
                    'priority' => 'string',
                    'query' => 'string',
                    'strict' => 'string',
                ],
            ],
            'delete_by_memory_id' => [
                'description' => 'Delete a specific memory by its ID',
                'required' => ['memory_id'],
                'allowed' => ['memory_id'],
                'types' => [
                    'memory_id' => 'integer',
                ],
            ],
            'get_by_memory_id' => [
                'description' => 'Retrieve a specific memory by its ID',
                'required' => ['memory_id'],
                'allowed' => ['memory_id'],
                'types' => [
                    'memory_id' => 'integer',
                ],
            ],
            'get_canonical_tags' => [
                'description' => 'List all canonical tag mappings',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_memory_stats' => [
                'description' => 'Retrieve memory system statistics',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_tag_frequencies' => [
                'description' => 'Get tag usage frequencies across memories',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_tag_weights' => [
                'description' => 'Get computed tag weights',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_unique_tags' => [
                'description' => 'List all unique tags in the memory store',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'list_recent_memories' => [
                'description' => 'List most recent memories with optional limit',
                'required' => [],
                'allowed' => ['limit'],
                'types' => [
                    'limit' => 'integer',
                ],
            ],
            'search' => [
                'description' => 'Search memories by query using semantic similarity',
                'required' => ['query'],
                'allowed' => ['category', 'limit', 'offset', 'query', 'tags'],
                'types' => [
                    'category' => 'string',
                    'limit' => 'integer',
                    'offset' => 'integer',
                    'query' => 'string',
                    'tags' => 'array',
                ],
            ],
            'search_memories' => [
                'description' => 'Search memories by query, category, or tags',
                'required' => [],
                'allowed' => ['category', 'limit', 'offset', 'query', 'tags'],
                'types' => [
                    'category' => 'string',
                    'limit' => 'integer',
                    'offset' => 'integer',
                    'query' => 'string',
                    'tags' => 'array',
                ],
            ],
            'stats' => [
                'description' => 'Get memory system statistics and health status',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'store_memory' => [
                'description' => 'Store a new memory with optional category and tags',
                'required' => ['content'],
                'allowed' => ['category', 'content', 'tags'],
                'types' => [
                    'category' => 'string',
                    'content' => 'string',
                    'tags' => 'array',
                ],
            ],
            'upsert' => [
                'description' => 'Store or update a memory with optional category and tags',
                'required' => ['content'],
                'allowed' => ['category', 'content', 'tags'],
                'types' => [
                    'category' => 'string',
                    'content' => 'string',
                    'tags' => 'array',
                ],
            ],
        ];
    }
}
