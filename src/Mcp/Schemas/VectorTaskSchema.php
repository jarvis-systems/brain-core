<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class VectorTaskSchema
{
    public static function get(): array
    {
        return [
            'task_create' => [
                'required' => ['title', 'content'],
                'allowed' => ['title', 'content', 'parent_id', 'comment', 'priority', 'estimate', 'order', 'parallel', 'tags'],
                'types' => [
                    'title' => 'string',
                    'content' => 'string',
                    'parent_id' => 'integer',
                    'comment' => 'string',
                    'priority' => 'string',
                    'estimate' => 'float',
                    'order' => 'integer',
                    'parallel' => 'boolean',
                    'tags' => 'array',
                ],
            ],
            'task_create_bulk' => [
                'required' => ['tasks'],
                'allowed' => ['tasks'],
                'types' => [
                    'tasks' => 'array',
                ],
            ],
            'task_update' => [
                'required' => ['task_id'],
                'allowed' => ['task_id', 'title', 'content', 'status', 'parent_id', 'comment', 'start_at', 'finish_at', 'priority', 'estimate', 'order', 'parallel', 'tags', 'append_comment', 'add_tag', 'remove_tag'],
                'types' => [
                    'task_id' => 'integer',
                    'title' => 'string',
                    'content' => 'string',
                    'status' => 'string',
                    'parent_id' => 'integer',
                    'comment' => 'string',
                    'start_at' => 'string',
                    'finish_at' => 'string',
                    'priority' => 'string',
                    'estimate' => 'float',
                    'order' => 'integer',
                    'parallel' => 'boolean',
                    'tags' => 'array',
                    'append_comment' => 'boolean',
                    'add_tag' => 'string',
                    'remove_tag' => 'string',
                ],
            ],
            'task_get' => [
                'required' => ['task_id'],
                'allowed' => ['task_id'],
                'types' => [
                    'task_id' => 'integer',
                ],
            ],
            'task_list' => [
                'required' => [],
                'allowed' => ['query', 'status', 'parent_id', 'tags', 'ids', 'limit', 'offset'],
                'types' => [
                    'query' => 'string',
                    'status' => 'string',
                    'parent_id' => 'integer',
                    'tags' => 'array',
                    'ids' => 'array',
                    'limit' => 'integer',
                    'offset' => 'integer',
                ],
            ],
            'task_delete' => [
                'required' => ['task_id'],
                'allowed' => ['task_id'],
                'types' => [
                    'task_id' => 'integer',
                ],
            ],
            'task_delete_bulk' => [
                'required' => ['task_ids'],
                'allowed' => ['task_ids'],
                'types' => [
                    'task_ids' => 'array',
                ],
            ],
            'task_comment' => [
                'required' => ['task_id', 'comment'],
                'allowed' => ['task_id', 'comment'],
                'types' => [
                    'task_id' => 'integer',
                    'comment' => 'string',
                ],
            ],
            'task_add_tag' => [
                'required' => ['task_id', 'tag'],
                'allowed' => ['task_id', 'tag'],
                'types' => [
                    'task_id' => 'integer',
                    'tag' => 'string',
                ],
            ],
            'task_remove_tag' => [
                'required' => ['task_id', 'tag'],
                'allowed' => ['task_id', 'tag'],
                'types' => [
                    'task_id' => 'integer',
                    'tag' => 'string',
                ],
            ],
            'task_next' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'task_stats' => [
                'required' => [],
                'allowed' => ['status', 'priority', 'tags', 'parent_id', 'created_after', 'created_before', 'start_after', 'start_before', 'finish_after', 'finish_before'],
                'types' => [
                    'status' => 'string',
                    'priority' => 'string',
                    'tags' => 'array',
                    'parent_id' => 'integer',
                    'created_after' => 'string',
                    'created_before' => 'string',
                    'start_after' => 'string',
                    'start_before' => 'string',
                    'finish_after' => 'string',
                    'finish_before' => 'string',
                ],
            ],
            'tag_frequencies' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'tag_weights' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'tag_classify' => [
                'required' => ['tag'],
                'allowed' => ['tag'],
                'types' => [
                    'tag' => 'string',
                ],
            ],
            'tags_classify_batch' => [
                'required' => ['tags'],
                'allowed' => ['tags'],
                'types' => [
                    'tags' => 'array',
                ],
            ],
            'search_explain' => [
                'required' => ['query'],
                'allowed' => ['query', 'limit'],
                'types' => [
                    'query' => 'string',
                    'limit' => 'integer',
                ],
            ],
            'tag_normalize_preview' => [
                'required' => [],
                'allowed' => ['threshold', 'require_predefined'],
                'types' => [
                    'threshold' => 'float',
                    'require_predefined' => 'boolean',
                ],
            ],
            'tag_normalize_apply' => [
                'required' => [],
                'allowed' => ['threshold', 'dry_run', 'require_predefined'],
                'types' => [
                    'threshold' => 'float',
                    'dry_run' => 'boolean',
                    'require_predefined' => 'boolean',
                ],
            ],
            'canonical_tag_add' => [
                'required' => ['canonical_tag', 'variant_tag'],
                'allowed' => ['canonical_tag', 'variant_tag'],
                'types' => [
                    'canonical_tag' => 'string',
                    'variant_tag' => 'string',
                ],
            ],
            'canonical_tag_remove' => [
                'required' => ['variant_tag'],
                'allowed' => ['variant_tag'],
                'types' => [
                    'variant_tag' => 'string',
                ],
            ],
            'canonical_tag_list' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'tag_similarity' => [
                'required' => ['tag1', 'tag2'],
                'allowed' => ['tag1', 'tag2'],
                'types' => [
                    'tag1' => 'string',
                    'tag2' => 'string',
                ],
            ],
            'get_canonical_tags' => [
                'required' => [],
                'allowed' => [],
                'types' => [],
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
