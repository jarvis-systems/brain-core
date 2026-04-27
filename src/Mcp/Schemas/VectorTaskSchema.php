<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class VectorTaskSchema
{
    public static function get(): array
    {
        return [
            'canonical_tag_add' => [
                'description' => 'Add a predefined canonical tag mapping',
                'required' => ['canonical_tag', 'variant_tag'],
                'allowed' => ['canonical_tag', 'variant_tag'],
                'types' => [
                    'canonical_tag' => 'string',
                    'variant_tag' => 'string',
                ],
            ],
            'canonical_tag_list' => [
                'description' => 'List predefined canonical tag mappings',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'canonical_tag_remove' => [
                'description' => 'Remove a predefined canonical tag mapping',
                'required' => ['variant_tag'],
                'allowed' => ['variant_tag'],
                'types' => [
                    'variant_tag' => 'string',
                ],
            ],
            'cookbook' => [
                'description' => 'Read vector task cookbook documentation and workflow cases',
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
            'get_canonical_tags' => [
                'description' => 'Get all canonical tags',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'search_explain' => [
                'description' => 'Search tasks with ranking explanation',
                'required' => ['query'],
                'allowed' => ['limit', 'query'],
                'types' => [
                    'limit' => 'integer',
                    'query' => 'string',
                ],
            ],
            'tag_classify' => [
                'description' => 'Classify a tag by search ranking boost level',
                'required' => ['tag'],
                'allowed' => ['tag'],
                'types' => [
                    'tag' => 'string',
                ],
            ],
            'tag_frequencies' => [
                'description' => 'Get tag frequencies and IDF weights',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'tag_normalize_apply' => [
                'description' => 'Apply tag normalization suggestions',
                'required' => [],
                'allowed' => ['dry_run', 'require_predefined', 'threshold'],
                'types' => [
                    'dry_run' => 'boolean',
                    'require_predefined' => 'boolean',
                    'threshold' => 'float',
                ],
            ],
            'tag_normalize_preview' => [
                'description' => 'Preview tag normalization suggestions',
                'required' => [],
                'allowed' => ['require_predefined', 'threshold'],
                'types' => [
                    'require_predefined' => 'boolean',
                    'threshold' => 'float',
                ],
            ],
            'tag_similarity' => [
                'description' => 'Calculate semantic similarity between two tags',
                'required' => ['tag1', 'tag2'],
                'allowed' => ['tag1', 'tag2'],
                'types' => [
                    'tag1' => 'string',
                    'tag2' => 'string',
                ],
            ],
            'tag_weights' => [
                'description' => 'Get IDF weights for all tags',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'tags_classify_batch' => [
                'description' => 'Classify multiple tags by search ranking boost level',
                'required' => ['tags'],
                'allowed' => ['tags'],
                'types' => [
                    'tags' => 'array',
                ],
            ],
            'task_add_tag' => [
                'description' => 'Add one tag to a task',
                'required' => ['tag', 'task_id'],
                'allowed' => ['tag', 'task_id'],
                'types' => [
                    'tag' => 'string',
                    'task_id' => 'integer',
                ],
            ],
            'task_comment' => [
                'description' => 'Append a comment to a task',
                'required' => ['comment', 'task_id'],
                'allowed' => ['comment', 'task_id'],
                'types' => [
                    'comment' => 'string',
                    'task_id' => 'integer',
                ],
            ],
            'task_create' => [
                'description' => 'Create a new vector task',
                'required' => ['content', 'title'],
                'allowed' => ['comment', 'content', 'estimate', 'order', 'parallel', 'parent_id', 'priority', 'tags', 'title'],
                'types' => [
                    'comment' => 'string',
                    'content' => 'string',
                    'estimate' => 'float',
                    'order' => 'integer',
                    'parallel' => 'boolean',
                    'parent_id' => 'integer',
                    'priority' => 'string',
                    'tags' => 'array',
                    'title' => 'string',
                ],
            ],
            'task_create_bulk' => [
                'description' => 'Create multiple vector tasks',
                'required' => ['tasks'],
                'allowed' => ['tasks'],
                'types' => [
                    'tasks' => 'array',
                ],
            ],
            'task_delete' => [
                'description' => 'Delete a task by ID',
                'required' => ['task_id'],
                'allowed' => ['task_id'],
                'types' => [
                    'task_id' => 'integer',
                ],
            ],
            'task_delete_bulk' => [
                'description' => 'Delete multiple tasks by ID',
                'required' => ['task_ids'],
                'allowed' => ['task_ids'],
                'types' => [
                    'task_ids' => 'array',
                ],
            ],
            'task_get' => [
                'description' => 'Get one task by ID',
                'required' => ['task_id'],
                'allowed' => ['task_id'],
                'types' => [
                    'task_id' => 'integer',
                ],
            ],
            'task_list' => [
                'description' => 'List tasks with optional filters and semantic search',
                'required' => [],
                'allowed' => ['ids', 'limit', 'offset', 'parent_id', 'query', 'status', 'tags'],
                'types' => [
                    'ids' => 'array',
                    'limit' => 'integer',
                    'offset' => 'integer',
                    'parent_id' => 'integer',
                    'query' => 'string',
                    'status' => 'string',
                    'tags' => 'array',
                ],
            ],
            'task_next' => [
                'description' => 'Get next task to work on',
                'required' => [],
                'allowed' => [],
                'types' => [],
            ],
            'task_remove_tag' => [
                'description' => 'Remove one tag from a task',
                'required' => ['tag', 'task_id'],
                'allowed' => ['tag', 'task_id'],
                'types' => [
                    'tag' => 'string',
                    'task_id' => 'integer',
                ],
            ],
            'task_stats' => [
                'description' => 'Get task statistics with optional filters',
                'required' => [],
                'allowed' => ['created_after', 'created_before', 'finish_after', 'finish_before', 'parent_id', 'priority', 'start_after', 'start_before', 'status', 'tags'],
                'types' => [
                    'created_after' => 'string',
                    'created_before' => 'string',
                    'finish_after' => 'string',
                    'finish_before' => 'string',
                    'parent_id' => 'integer',
                    'priority' => 'string',
                    'start_after' => 'string',
                    'start_before' => 'string',
                    'status' => 'string',
                    'tags' => 'array',
                ],
            ],
            'task_update' => [
                'description' => 'Update task fields by ID',
                'required' => ['task_id'],
                'allowed' => ['add_tag', 'append_comment', 'comment', 'content', 'estimate', 'finish_at', 'order', 'parallel', 'parent_id', 'priority', 'remove_tag', 'start_at', 'status', 'tags', 'task_id', 'title'],
                'types' => [
                    'add_tag' => 'string',
                    'append_comment' => 'boolean',
                    'comment' => 'string',
                    'content' => 'string',
                    'estimate' => 'float',
                    'finish_at' => 'string',
                    'order' => 'integer',
                    'parallel' => 'boolean',
                    'parent_id' => 'integer',
                    'priority' => 'string',
                    'remove_tag' => 'string',
                    'start_at' => 'string',
                    'status' => 'string',
                    'tags' => 'array',
                    'task_id' => 'integer',
                    'title' => 'string',
                ],
            ],
        ];
    }
}
