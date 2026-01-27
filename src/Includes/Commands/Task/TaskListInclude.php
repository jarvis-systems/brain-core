<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Task listing utility that queries vector storage and displays formatted task hierarchy with status and priority indicators. Supports filters: status, parent_id, tags, priority, limit.')]
class TaskListInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('LIST_FILTERS', '{filters extracted from $RAW_INPUT: status, parent_id, tags, query}'));

        // Role definition
        $this->guideline('role')
            ->text('Task listing utility that queries vector storage and displays formatted task hierarchy with status and priority indicators.');

        // Workflow Step 1 - Parse Filters
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse Input for Filters')
            ->example()
            ->phase('parse', 'Extract filters from $RAW_INPUT: status=pending, parent_id=5, tags=backend, priority=high, limit=20')
            ->phase('defaults', 'Default: no filters (list all), limit=50')
            ->phase('output', Store::as('FILTERS', '{status?, parent_id?, tags?, priority?, limit?, offset?}'));

        // Workflow Step 2 - Query Tasks
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Query Vector Task Storage')
            ->example()
            ->phase('query', VectorTaskMcp::call('task_list', Store::get('FILTERS')))
            ->phase('output', Store::as('TASKS', 'task list from vector storage'));

        // Workflow Step 3 - Format Output
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Format and Display Task List')
            ->example()
            ->phase('organize', 'Group tasks: root tasks (parent_id=null) first, then children indented under parents')
            ->phase('format', Operator::forEach(
                'task in ' . Store::get('TASKS'),
                'Display: {status_icon} {priority_icon} #{id} {title} [{tags}] (est: {estimate})'
            ))
            ->phase('summary', 'Show: total count, by status breakdown, by priority breakdown');

        // Status Indicators
        $this->guideline('status-icons')
            ->text('Status indicator mapping')
            ->example('[pending]')->key('pending')
            ->example('[in_progress]')->key('in_progress')
            ->example('[completed]')->key('completed')
            ->example('[stopped]')->key('stopped');

        // Priority Indicators
        $this->guideline('priority-icons')
            ->text('Priority indicator mapping')
            ->example('[critical]')->key('critical')
            ->example('[high]')->key('high')
            ->example('[medium]')->key('medium')
            ->example('[low]')->key('low');

        // Output Format
        $this->guideline('output-format')
            ->text('Task display format')
            ->example()
            ->phase('root', '{status} {priority} #{id} {title} [{tags}]')
            ->phase('child', '  └─ {status} {priority} #{id} {title} [{tags}]')
            ->phase('nested', '    └─ {status} {priority} #{id} {title} [{tags}]');

        // Filter Examples
        $this->guideline('filter-examples')
            ->text('Supported filter combinations')
            ->example('/task:list → all tasks')->key('all')
            ->example('/task:list status=pending → pending tasks only')->key('status')
            ->example('/task:list parent_id=5 → children of task #5')->key('parent')
            ->example('/task:list tags=backend,api → tasks with specific tags')->key('tags')
            ->example('/task:list priority=high → high priority tasks')->key('priority')
            ->example('/task:list status=pending priority=critical → combined filters')->key('combined');
    }
}
