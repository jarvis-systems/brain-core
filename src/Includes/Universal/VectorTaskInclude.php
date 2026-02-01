<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose(<<<'PURPOSE'
Vector task MCP protocol for hierarchical task management.
Task-first workflow: EXPLORE → EXECUTE → UPDATE.
Supports unlimited nesting via parent_id for flexible decomposition.
Maximize search flexibility. Explore tasks thoroughly. Preserve critical context via comments.
PURPOSE
)]
class VectorTaskInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // Task-First Protocol with Deep Exploration
        $this->guideline('task-first-workflow')
            ->text('Universal workflow: EXPLORE → EXECUTE → UPDATE. Always understand task context before starting.')
            ->example()
            ->phase('explore', VectorTaskMcp::call('task_get', '{task_id}') . ' → STORE-AS($TASK) → IF($TASK.parent_id) → ' . VectorTaskMcp::call('task_get', '{task_id: $TASK.parent_id}') . ' → STORE-AS($PARENT) [READ-ONLY context, NEVER modify] → ' . VectorTaskMcp::call('task_list', '{parent_id: $TASK.id}') . ' → STORE-AS($CHILDREN)')
            ->phase('start', VectorTaskMcp::call('task_update', '{task_id: $TASK.id, status: "in_progress"}') . ' [ONLY $TASK, NEVER $PARENT]')
            ->phase('execute', 'Perform task work. Add comments for critical discoveries (memory IDs, file paths, blockers).')
            ->phase('complete', VectorTaskMcp::call('task_update', '{task_id: $TASK.id, status: "completed", comment: "Done. Key findings stored in memory #ID.", append_comment: true}') . ' [ONLY $TASK]');

        // Full MCP Tools Reference with ALL Parameters
        $this->guideline('mcp-tools-create')
            ->text('Task creation tools with full parameters.')
            ->example(VectorTaskMcp::call('task_create', '{title, content, parent_id?, comment?, priority?, estimate?, order?, tags?}'))->key('create')
            ->example(VectorTaskMcp::call('task_create_bulk', '{tasks: [{title, content, parent_id?, comment?, priority?, estimate?, order?, tags?}, ...]}'))->key('bulk')
            ->example('title: short name (max 200 chars) | content: full description (max 10K chars)')->key('title-content')
            ->example('parent_id: link to parent task | comment: initial note | priority: low/medium/high/critical')->key('parent-comment')
            ->example('estimate: hours (float) | order: position (auto if null) | tags: ["tag1", "tag2"] (max 10)')->key('estimate-order-tags');

        $this->guideline('mcp-tools-read')
            ->text('Task reading tools. USE FULL SEARCH POWER - combine parameters for precise results.')
            ->example(VectorTaskMcp::call('task_get', '{task_id}') . ' - Get single task by ID')->key('get')
            ->example(VectorTaskMcp::call('task_next', '{}') . ' - Smart: returns in_progress OR next pending')->key('next')
            ->example(VectorTaskMcp::call('task_list', '{query?, status?, parent_id?, tags?, ids?, limit?, offset?}'))->key('list')
            ->example('query: semantic search in title+content (POWERFUL - use it!)')->key('query')
            ->example('status: pending|in_progress|completed|stopped | parent_id: filter subtasks | tags: ["tag"] (OR logic)')->key('filters')
            ->example('ids: [1,2,3] filter specific tasks (max 50) | limit: 1-50 (default 10) | offset: pagination')->key('ids-pagination');

        $this->guideline('mcp-tools-update')
            ->text('Task update with ALL parameters. One tool for everything: status, content, comments, tags.')
            ->example(VectorTaskMcp::call('task_update', '{task_id, title?, content?, status?, parent_id?, comment?, start_at?, finish_at?, priority?, estimate?, order?, tags?, append_comment?, add_tag?, remove_tag?}'))->key('full')
            ->example('status: "pending"|"in_progress"|"completed"|"stopped"')->key('status')
            ->example('comment: "text" | append_comment: true (append with \\n\\n separator) | false (replace)')->key('comment')
            ->example('add_tag: "single_tag" (validates duplicates, 10-tag limit) | remove_tag: "tag" (case-insensitive)')->key('tags')
            ->example('start_at/finish_at: AUTO-MANAGED (NEVER set manually, only for user-requested corrections) | estimate: hours | order: triggers sibling reorder')->key('timestamps');

        $this->guideline('mcp-tools-delete')
            ->text('Task deletion (permanent, cannot be undone).')
            ->example(VectorTaskMcp::call('task_delete', '{task_id}') . ' - Delete single task')->key('delete')
            ->example(VectorTaskMcp::call('task_delete_bulk', '{task_ids: [1, 2, 3]}') . ' - Delete multiple tasks')->key('bulk');

        $this->guideline('mcp-tools-stats')
            ->text('Statistics with powerful filtering. Use for overview and analysis.')
            ->example(VectorTaskMcp::call('task_stats', '{created_after?, created_before?, start_after?, start_before?, finish_after?, finish_before?, status?, priority?, tags?, parent_id?}'))->key('full')
            ->example('Returns: total, by_status (pending/in_progress/completed/stopped), with_subtasks, next_task_id, unique_tags')->key('returns')
            ->example('Date filters: ISO 8601 format (YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS)')->key('dates')
            ->example('parent_id: 0 for root tasks only | N for specific parent subtasks')->key('parent');

        // Deep Exploration Guidelines
        $this->guideline('deep-exploration')
            ->text('ALWAYS explore task hierarchy before execution. Understand parent context and child dependencies.')
            ->example()
            ->phase('up', 'IF(task.parent_id) → fetch parent → understand broader goal and constraints')
            ->phase('down', VectorTaskMcp::call('task_list', '{parent_id: task_id}') . ' → fetch children → understand subtask structure')
            ->phase('siblings', VectorTaskMcp::call('task_list', '{parent_id: task.parent_id}') . ' → fetch siblings → understand parallel work')
            ->phase('semantic', VectorTaskMcp::call('task_list', '{query: "related keywords"}') . ' → find related tasks across hierarchy');

        $this->guideline('search-flexibility')
            ->text('Maximize search power. Combine parameters. Use semantic query for discovery.')
            ->example('Find related: ' . VectorTaskMcp::call('task_list', '{query: "authentication", tags: ["backend"], status: "completed", limit: 5}'))->key('combined')
            ->example('Subtask analysis: ' . VectorTaskMcp::call('task_list', '{parent_id: 15, status: "pending"}'))->key('subtasks')
            ->example('Batch lookup: ' . VectorTaskMcp::call('task_list', '{ids: [1,2,3,4,5]}'))->key('batch')
            ->example('Semantic discovery: ' . VectorTaskMcp::call('task_list', '{query: "similar problem description"}'))->key('semantic');

        // Comment Best Practices
        $this->guideline('comment-strategy')
            ->text('Comments preserve CRITICAL context between sessions. Vector memory is PRIMARY storage.')
            ->example('ALWAYS append: append_comment: true (never lose previous context)')->key('append')
            ->example('Memory links: "Findings stored in memory #42, #43. See related #38."')->key('memory-links')
            ->example('File references: "Modified: src/Auth/Login.php:45-78. Created: tests/AuthTest.php"')->key('file-refs')
            ->example('Blockers: "BLOCKED: waiting for API spec. Resume when #15 completed."')->key('blockers')
            ->example('Decisions: "Chose JWT over sessions. Rationale in memory #50."')->key('decisions');

        $this->guideline('memory-task-relationship')
            ->text('Vector memory = PRIMARY knowledge. Task comments = CRITICAL links only.')
            ->example('Store detailed findings → vector memory | Store memory ID → task comment')->key('split')
            ->example('Long analysis/code → memory | Short reference "see memory #ID" → comment')->key('length')
            ->example('Reusable knowledge → memory | Task-specific state → comment')->key('reusability')
            ->example('Search vector memory BEFORE task | Link memory IDs IN task comment AFTER')->key('workflow');

        // Hierarchy via parent_id
        $this->guideline('hierarchy')
            ->text('Flexible hierarchy via parent_id. Unlimited nesting depth.')
            ->example('parent_id: null → root task (goal, milestone, epic)')->key('root')
            ->example('parent_id: N → child of task N (subtask, step, action)')->key('child')
            ->example('Depth determined by parent chain, not fixed levels')->key('depth')
            ->example('Use tags for cross-cutting categorization (not hierarchy)')->key('tags');

        // Decomposition
        $this->guideline('decomposition')
            ->text('Break large tasks into manageable children. Each child ≤ 4 hours estimated.')
            ->example()
            ->phase('when', 'Task estimate > 8 hours OR multiple distinct deliverables')
            ->phase('how', 'Create children with parent_id = current task, inherit priority')
            ->phase('criteria', 'Logical separation, clear dependencies, parallelizable when possible')
            ->phase('stop', 'When leaf task is atomic: single file/feature, ≤ 4h estimate');

        // Status Flow
        $this->guideline('status-flow')
            ->text('Task status lifecycle. Only ONE task in_progress at a time.')
            ->example('pending → in_progress → completed')->key('happy')
            ->example('pending → in_progress → stopped → in_progress → completed')->key('paused')
            ->example('On stop: add comment explaining WHY stopped and WHAT remains')->key('stop-comment');

        // Priority
        $this->guideline('priority')
            ->text('Priority levels: critical > high > medium > low.')
            ->example('Children inherit parent priority unless overridden')->key('inherit')
            ->example('Default: medium | Critical: blocking others | Low: nice-to-have')->key('usage');

        // Critical Rules
        $this->rule('mcp-only-access')->critical()
            ->text('ALL task operations MUST use MCP tools.')
            ->why('MCP ensures embedding generation and data integrity.')
            ->onViolation('Use ' . VectorTaskMcp::id() . ' tools.');

        $this->rule('explore-before-execute')->critical()
            ->text('MUST explore task context (parent, children, related) BEFORE starting execution.')
            ->why('Prevents duplicate work, ensures alignment with broader goals, discovers dependencies.')
            ->onViolation(VectorTaskMcp::call('task_get', '{task_id}') . ' + parent + children BEFORE ' . VectorTaskMcp::call('task_update', '{status: "in_progress"}'));

        $this->rule('single-in-progress')->high()
            ->text('Only ONE task should be in_progress at a time per agent.')
            ->why('Prevents context switching and ensures focus.')
            ->onViolation(VectorTaskMcp::call('task_update', '{task_id, status: "completed"}') . ' current before starting new.');

        $this->rule('parent-child-integrity')->high()
            ->text('Parent cannot be completed while children are pending/in_progress.')
            ->why('Ensures hierarchical consistency.')
            ->onViolation('Complete or stop all children first.');

        $this->rule('memory-primary-comments-critical')->high()
            ->text('Vector memory is PRIMARY storage. Task comments for CRITICAL context links only.')
            ->why('Memory is searchable, persistent, shared. Comments are task-local. Duplication wastes space.')
            ->onViolation('Move detailed content to memory. Keep only IDs/paths/references in comments.');

        $this->rule('estimate-required')->critical()
            ->text('EVERY task MUST have estimate in hours. No task without estimate.')
            ->why('Estimates enable planning, prioritization, progress tracking, and decomposition decisions.')
            ->onViolation('Add estimate parameter: ' . VectorTaskMcp::call('task_update', '{task_id, estimate: hours}') . '. Leaf tasks ≤4h, parent tasks = sum of children.');

        $this->rule('order-siblings')->high()
            ->text('Sibling tasks (same parent_id) SHOULD have explicit order for execution sequence.')
            ->why('Order defines execution priority within same level. Prevents ambiguity in task selection.')
            ->onViolation('Set order parameter: ' . VectorTaskMcp::call('task_update', '{task_id, order: N}') . '. Sequential: 1, 2, 3. Parallel: same order.');

        $this->rule('timestamps-auto')->critical()
            ->text('NEVER set start_at/finish_at manually. Timestamps are AUTO-MANAGED by system on status change.')
            ->why('System sets start_at when status→in_progress, finish_at when status→completed/stopped. Manual values corrupt timeline.')
            ->onViolation('Remove start_at/finish_at from task_update call. Use ONLY for corrections when explicitly requested by user.');

        $this->rule('parent-readonly')->critical()
            ->text('$PARENT task is READ-ONLY context. NEVER call task_update on parent task. NEVER attempt to change parent status. Parent hierarchy is managed by operator/automation OUTSIDE agent/command scope. Agent scope = assigned $TASK only.')
            ->why('Parent task lifecycle is managed externally. Agents must not interfere with parent status. Prevents infinite loops, hierarchy corruption, and scope creep.')
            ->onViolation('ABORT any task_update targeting parent_id. Only task_update on assigned $TASK is allowed.');
    }
}
