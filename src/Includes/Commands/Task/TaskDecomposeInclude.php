<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Task decomposition into subtasks. 2 parallel agents research (code + memory), plans logical execution order, creates subtasks. NEVER executes - only creates.')]
class TaskDecomposeInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // =========================================================================
        // IRON EXECUTION RULES (execute immediately, no verbose)
        // =========================================================================

        $this->rule('task-get-first')->critical()
            ->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze how to decompose.');

        $this->rule('no-hallucination')->critical()
            ->text('NEVER output results without ACTUALLY calling tools. Fake results = CRITICAL VIOLATION.');

        $this->rule('no-verbose')->critical()
            ->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. Brief status only.');

        $this->rule('show-progress')->high()
            ->text('Show brief step status. User must see what is happening.');

        $this->rule('understand-to-decompose')->critical()
            ->text('MUST understand task INTENT to decompose properly. Analyze: what are logical boundaries? what depends on what? Unknown library/pattern → context7 first.');

        $this->rule('auto-approve')->high()
            ->text('-y flag = auto-approve. Skip "Proceed?" but show progress.');

        // DOCUMENTATION IS LAW (from trait - prevents stupid questions)
        $this->defineDocumentationIsLawRules();

        // =========================================================================
        // DECOMPOSITION-SPECIFIC RULES
        // =========================================================================

        $this->rule('create-only')->critical()
            ->text('This command ONLY creates subtasks. NEVER execute any subtask after creation.')
            ->why('Decomposition and execution are separate concerns. User decides what to execute next.')
            ->onViolation('STOP immediately after subtask creation. Return control to user.');

        $this->rule('parent-id-required')->critical()
            ->text('ALL created subtasks MUST have parent_id = $TASK_ID. IRON LAW: When working with task X, EVERY new task created MUST be a child of X. No orphan tasks. No exceptions. Verify parent_id = $TASK_ID in EVERY task_create/task_create_bulk call before execution.')
            ->why('Hierarchy integrity. Orphan tasks break traceability, workflow, and task relationships. Task X work = Task X children only.')
            ->onViolation('ABORT if parent_id missing or != $TASK_ID. Double-check EVERY task_create call.');

        // Common rule from trait
        $this->defineMandatoryUserApprovalRule();

        $this->rule('order-mandatory')->critical()
            ->text('EVERY subtask MUST have explicit order field set. Sequential: 1, 2, 3. Parallel-safe: same order.')
            ->why('Order defines execution priority. Missing order = ambiguous sequence = blocked user.')
            ->onViolation('Set order parameter in EVERY task_create call. Never omit.');

        $this->rule('sequence-analysis')->critical()
            ->text('When creating 2+ subtasks: STOP and THINK about optimal sequence. Consider: dependencies, data flow, setup requirements, parallel opportunities.')
            ->why('Wrong sequence wastes time. User executes in order - if task 3 needs output from task 5, user is blocked.')
            ->onViolation('Use SequentialThinking to analyze dependencies. Reorder before creation.');

        $this->rule('logical-order')->high()
            ->text('Subtasks MUST be in logical execution order. Dependencies first, dependents after.')
            ->why('Prevents blocked work. User can execute subtasks sequentially without dependency issues.')
            ->onViolation('Reorder subtasks. Use SequentialThinking for complex dependencies.');

        $this->rule('exclude-brain-directory')->high()
            ->text('NEVER analyze '.Runtime::BRAIN_DIRECTORY.' when decomposing code tasks.')
            ->why('Brain system internals are not project code.')
            ->onViolation('Skip '.Runtime::BRAIN_DIRECTORY.' in all exploration.');

        // =========================================================================
        // INPUT CAPTURE
        // =========================================================================

        $this->defineInputCaptureWithTaskIdGuideline();

        // =========================================================================
        // WORKFLOW (single unified flow)
        // =========================================================================

        $this->guideline('workflow')
            ->goal('Decompose task into subtasks: load → research → plan → approve → create')
            ->example()

            // Stage 1: Load
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $TASK_ID}') . ' → ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID, limit: 50}') . ' → ' . Store::as('EXISTING_SUBTASKS'))
            ->phase(Operator::if('EXISTING_SUBTASKS.count > 0 AND NOT $HAS_AUTO_APPROVE', 'Ask: "(1) Add more, (2) Replace all, (3) Abort"'))

            // Stage 2: Research (parallel)
            ->phase(Operator::if('unknown library/pattern in task', Context7Mcp::call('query-docs', '{query: "{library/pattern}"}') . ' → understand before decomposing'))
            ->phase(Operator::parallel([
                TaskTool::agent('explore', 'DECOMPOSE RESEARCH: task #{$TASK.id}. Find: files, components, dependencies, split boundaries. EXCLUDE: ' . Runtime::BRAIN_DIRECTORY . '. Return: {files, components, boundaries}'),
                VectorMemoryMcp::call('search_memories', '{query: "decomposition patterns, similar tasks", limit: 5}') . ' → ' . Store::as('MEMORY_INSIGHTS'),
            ]))
            ->phase(Store::as('CODE_INSIGHTS', '{from explore agent}'))

            // Stage 3: Plan
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Synthesizing: CODE_INSIGHTS + MEMORY_INSIGHTS. Identify: boundaries, dependencies, parallel opportunities, order.",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase('Group by component, order by dependency, estimate each')
            ->phase(Store::as('SUBTASK_PLAN', '[{title, content, estimate, priority, order}]'))

            // Stage 4: Approve
            ->phase('Show: | Order | Subtask | Est | Priority |')
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved', 'Ask: "Create {count} subtasks? (yes/no/modify)"'))

            // Stage 5: Create
            ->phase(VectorTaskMcp::call('task_create_bulk', '{tasks: [{title, content, parent_id: $TASK_ID, priority, estimate, order, tags: ["decomposed"]}]}'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID}') . ' → verify')
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Decomposed #{$TASK.id} into {count} subtasks", category: "tool-usage"}'))
            ->phase('STOP: Do NOT execute. Return control to user.');

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('agent fails', 'Continue with available data'))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild, re-submit'));
    }
}