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
        // CORE RULES (4 essential rules)
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
        // STAGE 1: LOAD
        // =========================================================================

        $this->guideline('stage1-load')
            ->goal('Load vector task, verify it exists, gather context')
            ->example()
            ->phase(Operator::output([
                '=== TASK:DECOMPOSE STARTED ===',
                '',
                '## STAGE 1: LOADING',
            ]))
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $TASK_ID}'))
            ->phase(Store::as('TASK', '{id, title, content, status, parent_id, priority, tags, estimate}'))
            ->phase(Operator::if('$TASK not found', [
                Operator::report('Task #$TASK_ID not found'),
                'ABORT',
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID, limit: 50}'))
            ->phase(Store::as('EXISTING_SUBTASKS', '{existing subtasks}'))
            ->phase(Operator::if('$EXISTING_SUBTASKS.count > 0', [
                Operator::output(['Task has {count} existing subtasks.']),
                'Ask: "(1) Add more, (2) Replace all, (3) Abort"',
                'WAIT for user choice',
            ]))
            ->phase('Analyze task type:')
            ->phase(Store::as('TASK_TYPE', '{type: code|docs|architecture|testing, domain: backend|frontend|api|devops}'))
            ->phase(Operator::output([
                'Task: #{$TASK.id} - {$TASK.title}',
                'Type: {$TASK_TYPE.type} | Domain: {$TASK_TYPE.domain}',
                'Existing subtasks: {$EXISTING_SUBTASKS.count}',
            ]));

        // =========================================================================
        // STAGE 2: RESEARCH (2 agents parallel)
        // =========================================================================

        $this->guideline('stage2-research')
            ->goal('2 agents research in PARALLEL: code/docs + memory')
            ->example()
            ->phase(Operator::output(['', '## STAGE 2: RESEARCH (2 agents parallel)']))
            ->phase('Launch 2 agents in PARALLEL:')
            ->phase(Operator::do([
                // Agent 1: ExploreMaster - code + documentation
                TaskTool::agent('explore',
                    'DECOMPOSITION RESEARCH for task #{$TASK.id}: "{$TASK.title}"

RESEARCH:
1. CODE STRUCTURE - Find affected files, classes, methods
2. DOCUMENTATION - brain docs "{keywords}" → Read relevant docs
3. DEPENDENCIES - What depends on what? What must change together?
4. NATURAL BOUNDARIES - Where to split the work?

EXCLUDE: '.Runtime::BRAIN_DIRECTORY.'

OUTPUT: {
  files: [{path, purpose, changes_needed}],
  components: [{name, files, dependencies}],
  boundaries: [{split_point, reason}],
  docs_found: [{path, relevant_requirements}]
}'),

                // Agent 2: VectorMaster - memory search
                VectorMaster::call(
                    Operator::task([
                        'MEMORY RESEARCH for decomposition of: $TASK.title',
                        'Multi-probe search:',
                        'Probe 1: "$TASK.title implementation" (code-solution)',
                        'Probe 2: "$TASK_TYPE.domain decomposition patterns" (tool-usage)',
                        'Probe 3: "similar task structure breakdown" (architecture)',
                        'EXTRACT: past decompositions, patterns, warnings, estimates',
                        'OUTPUT: actionable insights for subtask planning',
                    ]),
                    Operator::output('{memories_found:N, patterns:[], warnings:[], past_estimates:[]}'),
                    Store::as('MEMORY_INSIGHTS')
                ),
            ]))
            ->phase(Store::as('CODE_INSIGHTS', '{results from ExploreMaster}'))
            ->phase(Operator::output([
                'Research complete:',
                '- Files analyzed: {$CODE_INSIGHTS.files.count}',
                '- Components found: {$CODE_INSIGHTS.components.count}',
                '- Memory insights: {$MEMORY_INSIGHTS.memories_found}',
            ]));

        // =========================================================================
        // STAGE 3: PLAN
        // =========================================================================

        $this->guideline('stage3-plan')
            ->goal('Create subtask plan with logical execution order')
            ->example()
            ->phase(Operator::output(['', '## STAGE 3: PLANNING']))
            ->phase('Synthesize research into subtasks:')
            ->phase(Operator::do([
                'Step 1: Group related changes from $CODE_INSIGHTS.components',
                'Step 2: Apply patterns from $MEMORY_INSIGHTS',
                'Step 3: Determine dependencies between groups',
                'Step 4: Order by dependency (independent first)',
                'Step 5: Add estimates based on scope',
            ]))
            ->phase(Store::as('SUBTASK_PLAN', '[{title, content, estimate, priority, order, depends_on}]'))
            ->phase(Operator::if('count($SUBTASK_PLAN) >= 5', [
                Operator::output(['Complex decomposition (5+ subtasks). Using SequentialThinking for optimal order.']),
                SequentialThinkingMcp::call('sequentialthinking', '{
                    thought: "Planning execution order for {count} subtasks. Must ensure dependencies resolved before dependents. Analyzing: {$SUBTASK_PLAN}",
                    thoughtNumber: 1,
                    totalThoughts: 4,
                    nextThoughtNeeded: true
                }'),
                'Reorder $SUBTASK_PLAN based on SequentialThinking analysis',
            ]))
            ->phase('For EACH subtask:')
            ->phase(Operator::forEach('subtask in $SUBTASK_PLAN', [
                'title: Action-oriented, concise',
                'content: Scope, files, acceptance criteria',
                'estimate: hours (realistic)',
                'priority: inherit from parent',
                'tags: inherit parent + [decomposed]',
                'order: execution sequence (1, 2, 3...)',
            ]));

        // =========================================================================
        // STAGE 4: APPROVE
        // =========================================================================

        $this->guideline('stage4-approve')
            ->goal('Present plan for user approval')
            ->example()
            ->phase(Operator::output(['', '## STAGE 4: APPROVAL']))
            ->phase('Display decomposition summary:')
            ->phase(Operator::output([
                '═══ DECOMPOSITION PLAN ═══',
                'Parent: #{$TASK.id} - {$TASK.title}',
                'Subtasks: {count}',
                '',
                '| # | Subtask | Est | Priority | Depends |',
                '|---|---------|-----|----------|---------|',
                '| 1 | {title} | {h}h | {pri} | - |',
                '| 2 | {title} | {h}h | {pri} | #1 |',
                '| ... |',
                '',
                'Total estimate: {sum}h',
                '═══════════════════════════',
            ]))
            ->phase(Operator::if('$HAS_Y_FLAG === true', [
                Operator::output(['✅ Auto-approved (-y flag)']),
            ]))
            ->phase(Operator::if('$HAS_Y_FLAG === false', [
                Operator::output(['Create {count} subtasks? (yes/no/modify)']),
                'WAIT for approval',
                Operator::verify('User approved'),
            ]));

        // =========================================================================
        // STAGE 5: CREATE
        // =========================================================================

        $this->guideline('stage5-create')
            ->goal('Create subtasks, store to memory, report')
            ->example()
            ->phase(Operator::output(['', '## STAGE 5: CREATING']))
            ->phase('Create subtasks via bulk:')
            ->phase(VectorTaskMcp::call('task_create_bulk', '{tasks: $SUBTASK_PLAN.map(s => ({
                title: s.title,
                content: s.content,
                parent_id: $TASK_ID,
                priority: s.priority,
                estimate: s.estimate,
                order: s.order,
                tags: [...$TASK.tags, "decomposed"]
            }))}'))
            ->phase(Store::as('CREATED_SUBTASKS', '[{id, title}]'))
            ->phase('Verify:')
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID}'))
            ->phase('Store decomposition insight:')
            ->phase(VectorMemoryMcp::call('store_memory', '{
                content: "DECOMPOSED|#{$TASK.id} {$TASK.title}|subtasks:{count}|order:{execution_order}|components:{from CODE_INSIGHTS}",
                category: "tool-usage",
                tags: ["task-decomposition", "$TASK_TYPE.domain"]
            }'))
            ->phase(Operator::output([
                '',
                '═══ DECOMPOSITION COMPLETE ═══',
                'Created: {count} subtasks for #{$TASK_ID}',
                'Total estimate: {sum}h',
                '',
                'Next steps:',
                '  /task:list --parent={$TASK_ID} - view subtasks',
                '  /task:async {first_subtask_id} - start first subtask',
                '═══════════════════════════════',
            ]))
            ->phase('STOP: Do NOT execute any subtask. Return control to user.');

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