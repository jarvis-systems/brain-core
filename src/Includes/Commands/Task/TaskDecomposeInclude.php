<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Agents\VectorMaster;
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

        $this->rule('tool-call-first')->critical()
            ->text('YOUR VERY FIRST RESPONSE MUST BE A TOOL CALL. No text before tools. No analysis. No thinking out loud. CALL mcp__vector-task__task_get IMMEDIATELY with $TASK_ID.');

        $this->rule('no-hallucination')->critical()
            ->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');

        $this->rule('no-verbose')->critical()
            ->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action. Brief status updates ONLY.');

        $this->rule('show-progress')->high()
            ->text('ALWAYS show brief step status and results. User must see what is happening.');

        $this->rule('no-interpretation')->critical()
            ->text('NEVER interpret task content or give generic responses. Task ID given = decompose it. Follow the workflow EXACTLY.');

        $this->rule('auto-approve')->high()
            ->text('-y flag = auto-approve. Skip "Proceed?" questions, but STILL show progress.');

        // =========================================================================
        // DECOMPOSITION-SPECIFIC RULES
        // =========================================================================

        $this->rule('create-only')->critical()
            ->text('This command ONLY creates subtasks. NEVER execute any subtask after creation.')
            ->why('Decomposition and execution are separate concerns. User decides what to execute next.')
            ->onViolation('STOP immediately after subtask creation. Return control to user.');

        $this->rule('parent-id-required')->critical()
            ->text('ALL created subtasks MUST have parent_id = $TASK_ID.')
            ->why('Hierarchy integrity. Subtasks must link to parent task.')
            ->onViolation('Verify parent_id in every task_create call.');

        // Common rule from trait
        $this->defineMandatoryUserApprovalRule();

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
            ->phase(Operator::output(['=== TASK:DECOMPOSE ===', 'Loading task #$TASK_ID...']))
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $TASK_ID}').' → '.Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task #$TASK_ID not found')))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID, limit: 50}').' → '.Store::as('EXISTING_SUBTASKS'))
            ->phase(Operator::if('EXISTING_SUBTASKS.count > 0 AND NOT $HAS_Y_FLAG', [
                'Task has {count} existing subtasks.',
                'Ask: "(1) Add more, (2) Replace all, (3) Abort"',
                'WAIT for user choice',
            ]))
            ->phase(Operator::output([
                'Task: #{$TASK.id} - {$TASK.title}',
                'Status: {$TASK.status} | Priority: {$TASK.priority}',
                'Existing subtasks: {count}',
            ]))

            // Stage 2: Research (2 agents PARALLEL)
            ->phase(Operator::output(['', '## RESEARCH (2 agents parallel)']))
            ->phase('Launch 2 agents in PARALLEL (single message with multiple Task calls):')
            ->phase(Operator::do([
                TaskTool::agent('explore', 'DECOMPOSITION RESEARCH for task #{$TASK.id}: "{$TASK.title}". Find: files, components, dependencies, natural split boundaries. EXCLUDE: '.Runtime::BRAIN_DIRECTORY.'. OUTPUT: {files:[], components:[], boundaries:[]}'),
                VectorMaster::call(Operator::task(['Memory search for: task decomposition patterns, similar implementations, past estimates']), Store::as('MEMORY_INSIGHTS')),
            ]))
            ->phase(Store::as('CODE_INSIGHTS', '{from explore agent}'))

            // Stage 3: Plan
            ->phase(Operator::output(['', '## PLANNING']))
            ->phase('Create subtask plan: group by component, order by dependency, estimate each')
            ->phase(Store::as('SUBTASK_PLAN', '[{title, content, estimate, priority, order}]'))
            ->phase(Operator::if('5+ subtasks', SequentialThinkingMcp::call('sequentialthinking', '{thought: "Order subtasks by dependencies", thoughtNumber: 1, totalThoughts: 3, nextThoughtNeeded: true}')))

            // Stage 4: Approve
            ->phase(Operator::output(['', '## PLAN']))
            ->phase('Show table: | # | Subtask | Est | Priority | Depends |')
            ->phase(Operator::if('$HAS_Y_FLAG', Operator::output(['Auto-approved (-y flag)'])))
            ->phase(Operator::if('NOT $HAS_Y_FLAG', ['Ask: "Create {count} subtasks? (yes/no/modify)"', 'WAIT for approval']))

            // Stage 5: Create
            ->phase(Operator::output(['', '## CREATING']))
            ->phase(VectorTaskMcp::call('task_create_bulk', '{tasks: [{title, content, parent_id: $TASK_ID, priority, estimate, order, tags: [...$TASK.tags, "decomposed"]}]}'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID}').' → verify created')
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "DECOMPOSED|#{$TASK.id}|subtasks:{count}", category: "tool-usage", tags: ["task-decomposition"]}'))
            ->phase(Operator::output(['', '=== DECOMPOSITION COMPLETE ===', 'Created: {count} subtasks', 'Next: /task:list --parent={$TASK_ID}']))
            ->phase('STOP: Do NOT execute subtasks. Return control to user.');

        // =========================================================================
        // ERROR HANDLING
        // =========================================================================

        $this->guideline('error-handling')
            ->text('Graceful error recovery')
            ->example()
            ->phase()->if('task not found', ['Report error', 'Suggest task_list', 'ABORT'])
            ->phase()->if('agent fails', ['Log error', 'Continue with available data', 'Report partial results'])
            ->phase()->if('user rejects', ['Accept modifications', 'Rebuild plan', 'Re-submit for approval']);
    }
}