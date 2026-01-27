<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Validate vector task. 3 parallel agents: Code Quality, Testing, Documentation. Creates fix-tasks for issues. Cosmetic fixed inline.')]
class TaskValidateInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // RULES (compact)
        $this->rule('execute-always')->critical()->text('NEVER skip validation. Status "validated" = re-validate.');
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to validate. Task ID given = validate it. No "fix-task", "not a deliverable", "already validated" excuses. JUST EXECUTE.');
        $this->rule('cosmetic-inline')->critical()->text('Cosmetic (whitespace, typos, formatting, comment descriptions) = fix inline. Metadata tags (author, version, since) = IGNORE.');
        $this->rule('functional-to-task')->critical()->text('Functional issues (logic, security, architecture) = create fix-task, NEVER fix directly.');
        $this->rule('fix-task-required')->critical()->text('Issues found → MUST create fix-task AND set status=pending. No exceptions.');

        // EXECUTION DIRECTIVE
        $this->rule('execute-now')->critical()->text('This is NOT documentation. EXECUTE workflow immediately when command invoked. Do NOT ask questions, do NOT wait - START with step 1.');
        $this->rule('no-analysis')->critical()->text('NO verbose analysis, NO "let me think", NO meta-commentary. -y flag = SILENT execution. Just call tools and DO the work.');

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}'))
            ->phase('IF not found → ABORT')
            ->phase('IF status NOT IN [completed, tested, validated, in_progress] → ABORT "Complete first"')
            ->phase('IF status=in_progress → SESSION RECOVERY: check if crashed session (no active work) → continue validation OR ABORT if another session active')

            // 2. Approval (skip if -y)
            ->phase('IF $ARGUMENTS contains -y → skip approval')
            ->phase('ELSE → show task info, wait "yes"')
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "in_progress"}'))

            // 3. Validate (3 parallel agents)
            ->phase(TaskTool::agent('explore', 'CODE QUALITY: completeness, architecture, security, performance. Cosmetic=fix inline. Return issues list.'))
            ->phase(TaskTool::agent('explore', 'TESTING: coverage, quality, edge cases, error handling. Run tests. Cosmetic=fix inline. Return issues list.'))
            ->phase(TaskTool::agent('explore', 'DOCUMENTATION: docs sync, API docs, type hints, dependencies. Cosmetic=fix inline. IGNORE metadata tags. Return issues list.'))

            // 4. Finalize
            ->phase('Merge agent results → categorize: Critical/Major/Minor')
            ->phase('IF issues=0 →')
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "validated"}'))
            ->phase('IF issues>0 →')
            ->phase(VectorTaskMcp::call('task_create', '{title: "Validation fixes: #ID", content: issues_list, parent_id: task_id, tags: ["validation-fix"]}'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "pending"}'))

            // 5. Report
            ->phase('Output: task, Critical/Major/Minor counts, cosmetic fixed, status, fix-task ID')
            ->phase(VectorMemoryMcp::call('store_memory', '{content: validation_summary, category: "code-solution"}'));
    }
}
