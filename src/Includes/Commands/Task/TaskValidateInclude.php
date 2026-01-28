<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Validate vector task. 3 parallel agents: Code Quality, Testing, Documentation. Creates fix-tasks for issues. Cosmetic fixed inline.')]
class TaskValidateInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('tool-call-first')->critical()->text('YOUR VERY FIRST RESPONSE MUST BE A TOOL CALL. No text before tools. No analysis. No thinking out loud. CALL mcp__vector-task__task_get IMMEDIATELY.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status, validation results, or issues without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('no-verbose')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action.');
        $this->rule('show-progress')->high()->text('ALWAYS show brief step status and results. User must see what is happening and can interrupt/correct at any moment.');
        $this->rule('auto-approve')->high()->text('-y flag = auto-approve. Skip "Proceed?" questions, but STILL show progress. User sees everything, just no approval prompts.');

        // VALIDATION RULES
        $this->rule('execute-always')->critical()->text('NEVER skip validation. Status "validated" = re-validate.');
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to validate. Task ID given = validate it. No excuses. JUST EXECUTE.');
        $this->rule('cosmetic-inline')->critical()->text('Cosmetic (whitespace, typos, formatting) = fix inline. Metadata tags = IGNORE.');
        $this->rule('functional-to-task')->critical()->text('Functional issues (logic, security, architecture) = create fix-task, NEVER fix directly.');
        $this->rule('fix-task-blocks-validated')->critical()->text('If fix-task created → parent status MUST be "pending", NEVER "validated". "validated" = ZERO fix-tasks. NO EXCEPTIONS. "NOT blocking" still requires fix-task and pending status.');

        // Quality gates - commands that MUST pass for validation
        $qualityCommands = $this->groupVars('QUALITY_COMMAND');

        if (!empty($qualityCommands)) {
            $this->rule('quality-gates-mandatory')->critical()
                ->text('ALL quality commands below MUST be executed and PASS. Any failure = create fix-task. Cannot mark validated until ALL pass.');

            foreach ($qualityCommands as $key => $cmd) {
                $this->rule('quality-' . $key)
                    ->critical()
                    ->text("QUALITY GATE [{$key}]: {$cmd}");
            }
        }

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}') . ' ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase(Operator::if(
                'status NOT IN [completed, tested, validated, in_progress]',
                Operator::abort('Complete first')
            ))
            ->phase(Operator::if(
                'status=in_progress',
                'SESSION RECOVERY: check if crashed session (no active work)',
                Operator::abort('another session active')
            ))
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' ' . Store::as('PARENT')
            ))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: task_id}') . ' ' . Store::as('SUBTASKS'))

            // 2. Context gathering (memory + docs + related tasks)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' ' . Store::as('MEMORY_CONTEXT'))
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 5}') . ' ' . Store::as('RELATED_TASKS'))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' ' . Store::as('DOCS_INDEX'))

            // 3. Approval (skip if -y)
            ->phase(Operator::if(
                '$ARGUMENTS contains -y',
                Operator::skip('approval'),
                'show task info, wait "yes"'
            ))
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "in_progress"}'))

            // 4. Validate (3 parallel agents)
            ->phase(Operator::parallel([
                TaskTool::agent('explore', 'CODE QUALITY: completeness, architecture, security, performance. Cosmetic=fix inline. Return issues list.'),
                TaskTool::agent('explore', 'TESTING: coverage, quality, edge cases, error handling. Run tests. Cosmetic=fix inline. Return issues list.'),
                TaskTool::agent('explore', 'DOCUMENTATION: docs sync, API docs, type hints, dependencies. Cosmetic=fix inline. IGNORE metadata tags. Return issues list.'),
            ]))

            // 5. Finalize (CRITICAL: fix-task created = status MUST be "pending", NEVER "validated")
            ->phase('Merge agent results ' . Store::as('ISSUES') . ' categorize: Critical/Major/Minor')
            ->phase(Operator::if(
                'issues=0 AND no fix-task needed',
                VectorTaskMcp::call('task_update', '{task_id, status: "validated"}'),
                [
                    VectorTaskMcp::call('task_create', '{title: "Validation fixes: #ID", content: issues_list, parent_id: task_id, tags: ["validation-fix"]}'),
                    VectorTaskMcp::call('task_update', '{task_id, status: "pending"}') . ' ← MANDATORY when fix-task created',
                ]
            ))

            // 6. Report
            ->phase(Operator::output('task, Critical/Major/Minor counts, cosmetic fixed, status, fix-task ID'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: validation_summary, category: "code-solution"}'));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('task status invalid', Operator::abort('Complete first')))
            ->phase(Operator::if('agent fails', 'retry', 'continue with remaining agents'))
            ->phase(Operator::if('fix-task creation fails', 'store to memory for manual review'))
            ->phase(Operator::if('user rejects validation', 'accept modifications, re-validate'));
    }
}
