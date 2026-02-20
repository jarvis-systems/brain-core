<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class VectorMemorySchema
{
    public static function get(): array
    {
        return [
            'search_memories' => [
                'required' => [],
                'allowed' => ['query', 'limit', 'category', 'offset', 'tags'],
                'types' => [
                    'query' => 'string',
                    'limit' => 'integer',
                    'category' => 'string',
                    'offset' => 'integer',
                    'tags' => 'array',
                ],
            ],
            'store_memory' => [
                'required' => ['content'],
                'allowed' => ['content', 'category', 'tags'],
                'types' => [
                    'content' => 'string',
                    'category' => 'string',
                    'tags' => 'array',
                ],
            ],
            'get_by_memory_id' => [
                'required' => ['memory_id'],
                'allowed' => ['memory_id'],
                'types' => [
                    'memory_id' => 'integer',
                ],
            ],
            'delete_by_memory_id' => [
                'required' => ['memory_id'],
                'allowed' => ['memory_id'],
                'types' => [
                    'memory_id' => 'integer',
                ],
            ],
            'list_recent_memories' => [
                'required' => [],
                'allowed' => ['limit'],
                'types' => [
                    'limit' => 'integer',
                ],
            ],
            'get_unique_tags' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_canonical_tags' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_tag_frequencies' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_tag_weights' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'get_memory_stats' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'clear_old_memories' => [
                'required' => [],
                'allowed' => ['days_old', 'max_to_keep'],
                'types' => [
                    'days_old' => 'integer',
                    'max_to_keep' => 'integer',
                ],
            ],
            'cookbook' => [
                'required' => [],
                'allowed' => ['include', 'level', 'case_category', 'query', 'priority', 'cognitive', 'strict', 'limit', 'offset'],
                'types' => [
                    'include' => 'string',
                    'level' => 'integer',
                    'case_category' => 'string',
                    'query' => 'string',
                    'priority' => 'string',
                    'cognitive' => 'string',
                    'strict' => 'string',
                    'limit' => 'integer',
                    'offset' => 'integer',
                ],
            ],
        ];
    }
}
