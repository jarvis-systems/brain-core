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
        $this->rule('cosmetic-inline')->critical()->text('Cosmetic (whitespace, typos, formatting, comment descriptions) = fix inline. Metadata tags (author, version, since) = IGNORE.');
        $this->rule('functional-to-task')->critical()->text('Functional issues (logic, security, architecture) = create fix-task, NEVER fix directly.');
        $this->rule('fix-task-required')->critical()->text('Issues found → MUST create fix-task AND set status=pending. No exceptions.');

        // WORKFLOW - ACTION instruction
        $this->guideline('workflow')
            ->text('1. ' . VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}') . ' → task.content describes WHAT to validate')
            ->text('2. IF not found → ABORT')
            ->text('3. IF status NOT IN [completed, tested, validated] → ABORT "Complete first"')
            ->text('4. IF -y flag → skip approval, ELSE → show task info, wait "yes"')
            ->text('5. ' . VectorTaskMcp::call('task_update', '{task_id, status: "in_progress"}'))
            ->text('6. VALIDATE using 3 PARALLEL agents (single message with 3 Task() calls):')
            ->text('   ' . TaskTool::agent('explore', 'CODE QUALITY: completeness, architecture, security, performance. Cosmetic=fix inline. Return issues list.'))
            ->text('   ' . TaskTool::agent('explore', 'TESTING: coverage, quality, edge cases, error handling. Run tests. Cosmetic=fix inline. Return issues list.'))
            ->text('   ' . TaskTool::agent('explore', 'DOCUMENTATION: docs sync, API docs, type hints, dependencies. Cosmetic=fix inline. IGNORE metadata tags. Return issues list.'))
            ->text('7. Merge agent results → categorize: Critical/Major/Minor')
            ->text('8. IF issues=0 → ' . VectorTaskMcp::call('task_update', '{task_id, status: "validated"}'))
            ->text('9. IF issues>0 → ' . VectorTaskMcp::call('task_create', '{title: "Validation fixes: #ID", content: issues_list, parent_id: task_id, tags: ["validation-fix"]}') . ' + ' . VectorTaskMcp::call('task_update', '{task_id, status: "pending"}'))
            ->text('10. Output: task, Critical/Major/Minor counts, cosmetic fixed, status, fix-task ID')
            ->text('11. ' . VectorMemoryMcp::call('store_memory', '{content: validation_summary, category: "code-solution"}'));
    }
}