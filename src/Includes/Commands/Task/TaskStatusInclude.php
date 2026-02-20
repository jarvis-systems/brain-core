<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Provides detailed task status information based on user input. Supports time-based filters, status filters, grouping, and specific parent queries.')]
class TaskStatusInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // Summary rule
        $this->rule('always-summary')
            ->medium()
            ->text('Always show summary at the end of output')
            ->why('Provides quick overview regardless of query type')
            ->onViolation('Append: "Summary: {total} total, {completed} completed ({pct}%)"');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('STATUS_QUERY', '{query extracted from $RAW_INPUT}'));

        // Input parsing
        $this->guideline('parse-arguments')
            ->text('Parse captured input to detect query type')
            ->example()
            ->phase('detect', Store::as('QUERY_TYPE', '{detect: time|status|group|specific|default from $RAW_INPUT}'))
            ->example('Time filters: yesterday, today, this week, this month, last 7 days, last N days')->key('time')
            ->example('Status filters: completed, pending, in_progress, stopped')->key('status')
            ->example('Grouping: by priority, by tags, by parent')->key('group')
            ->example('Specific: parent_id=N, task_id=N')->key('specific')
            ->example('Empty: default overview statistics')->key('empty');

        // Route to appropriate handler
        $this->guideline('route-query')
            ->text('Route to handler based on detected query type')
            ->example()
            ->phase('route', Operator::if(
                Store::get('RAW_INPUT') . ' is empty',
                'Execute: default-stats workflow',
                'Execute: custom-query workflow based on ' . Store::get('QUERY_TYPE')
            ));

        // Default stats workflow (empty input)
        $this->guideline('default-stats')
            ->text('Default overview when ' . Store::get('RAW_INPUT') . ' is empty')
            ->example()
            ->phase('fetch', VectorTaskMcp::callValidatedJson('task_stats', []))
            ->phase('store', Store::as('STATS', 'statistics response'))
            ->phase('format', Operator::do(
                'Display: Total tasks: {total}',
                'Display: Pending: {pending} | In Progress: {in_progress} | Completed: {completed}',
                'Calculate: completion_pct = (completed / total) * 100',
                'Render: [####------] {completion_pct}%'
            ));

        // Time-based filter workflow
        $this->guideline('time-filter')
            ->text('Handle time-based filters when ' . Store::get('QUERY_TYPE') . ' = time')
            ->example()
            ->phase('parse', Operator::do(
                'yesterday → tasks from previous day',
                'today → tasks from current day',
                'this week → tasks from current week (Mon-Sun)',
                'this month → tasks from current month',
                'last N days → tasks from past N days'
            ))
            ->phase('fetch-completed', Operator::if(
                Store::get('RAW_INPUT') . ' contains "completed"',
                VectorTaskMcp::callValidatedJson('task_list', ['status' => 'completed', 'limit' => 50]),
                VectorTaskMcp::callValidatedJson('task_list', ['limit' => 50])
            ))
            ->phase('filter', 'Filter results by detected timeframe using task timestamps')
            ->phase('output', Operator::do(
                'List matching tasks with: title, status, created_at/completed_at',
                'Show count: "Found {N} tasks {timeframe}"'
            ));

        // Status filter workflow
        $this->guideline('status-filter')
            ->text('Handle status-based filters when ' . Store::get('QUERY_TYPE') . ' = status')
            ->example()
            ->phase('detect', 'Extract status: completed|pending|in_progress|stopped')
            ->phase('fetch', VectorTaskMcp::callValidatedJson('task_list', ['status' => '{detected_status}', 'limit' => 30]))
            ->phase('store', Store::as('TASKS', 'filtered task list'))
            ->phase('output', Operator::do(
                'Display: "{status}" tasks: {count}',
                'List each: #{id} {title} (priority: {priority}, created: {date})'
            ));

        // Grouping workflow
        $this->guideline('grouping')
            ->text('Handle grouping when ' . Store::get('QUERY_TYPE') . ' = group')
            ->example()
            ->phase('by-priority', Operator::if(
                Store::get('RAW_INPUT') . ' = "by priority"',
                Operator::do(
                    VectorTaskMcp::callValidatedJson('task_list', ['limit' => 100]),
                    'Group by priority: critical, high, medium, low',
                    'Display: Priority | Count | % of Total',
                    'Display: Critical: {n} ({pct}%)',
                    'Display: High: {n} ({pct}%)',
                    'Display: Medium: {n} ({pct}%)',
                    'Display: Low: {n} ({pct}%)'
                )
            ))
            ->phase('by-tags', Operator::if(
                Store::get('RAW_INPUT') . ' = "by tags"',
                Operator::do(
                    VectorTaskMcp::callValidatedJson('task_list', ['limit' => 100]),
                    'Extract and count unique tags',
                    'Display: Tag | Count',
                    'Sort by count descending'
                )
            ))
            ->phase('by-parent', Operator::if(
                Store::get('RAW_INPUT') . ' = "by parent"',
                Operator::do(
                    VectorTaskMcp::callValidatedJson('task_list', ['limit' => 100]),
                    'Group by parent_id (null = root tasks)',
                    'Display: Root tasks: {n}',
                    'Display: Child tasks by parent with counts'
                )
            ));

        // Specific parent query
        $this->guideline('specific-parent')
            ->text('Handle parent_id=N queries when ' . Store::get('QUERY_TYPE') . ' = specific')
            ->example()
            ->phase('parse', 'Extract N from "parent_id=N" in ' . Store::get('RAW_INPUT'))
            ->phase('fetch-parent', VectorTaskMcp::callValidatedJson('task_get', ['task_id' => 'N']))
            ->phase('fetch-children', VectorTaskMcp::callValidatedJson('task_list', ['parent_id' => 'N', 'limit' => 50]))
            ->phase('output', Operator::do(
                'Display parent: #{id} {title} [{status}]',
                'Display children count: {n} subtasks',
                'List children: #{id} {title} [{status}] (priority: {priority})',
                'Show completion: {completed}/{total} subtasks done'
            ));

        // Output format guidelines
        $this->guideline('output-format')
            ->text('Standard output formatting')
            ->example('--- Task Statistics ---')->key('header-default')
            ->example('--- Tasks: {query_description} ---')->key('header-custom')
            ->example('Total: 25 | Pending: 15 | In Progress: 2 | Completed: 8')->key('summary-line')
            ->example('[########----------] 32%')->key('progress-bar')
            ->example('#{id} {title} [{status}] - {date}')->key('task-item')
            ->example('Found 5 tasks completed yesterday')->key('filter-result')
            ->example('Priority breakdown: Critical(2) High(5) Medium(12) Low(6)')->key('grouping-result');
    }
}
