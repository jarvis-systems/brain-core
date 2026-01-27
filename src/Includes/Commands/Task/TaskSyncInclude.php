<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Tools\BashTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Direct sync execution of vector task by Brain. No agent delegation. Uses Read/Edit/Write/Glob/Grep directly.')]
class TaskSyncInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // RULES (compact)
        $this->rule('no-delegation')->critical()->text('Brain executes ALL steps directly. NO Task() delegation. Use ONLY: Read, Edit, Write, Glob, Grep, Bash.');
        $this->rule('single-approval')->critical()->text('ONE approval gate after plan. -y flag = auto-approve.');
        $this->rule('read-before-edit')->critical()->text('ALWAYS Read file BEFORE Edit/Write.');
        $this->rule('atomic-only')->critical()->text('Execute ONLY approved plan. NO improvisation.');

        // WORKFLOW - ACTION instruction, not output checklist
        $this->guideline('workflow')
            ->text('1. ' . VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}') . ' → task.content IS your work order')
            ->text('2. ' . VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5}'))
            ->text('3. ' . VectorTaskMcp::call('task_update', '{task_id, status: "in_progress"}'))
            ->text('4. EXECUTE task.content directly using Read/Edit/Write/Glob/Grep/Bash')
            ->text('5. ' . VectorTaskMcp::call('task_update', '{task_id, status: "completed"}'))
            ->text('6. ' . VectorMemoryMcp::call('store_memory', '{content: learnings, category: "code-solution"}'));

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase('IF task.comment contains "TDD MODE" AND status=tested:')
            ->phase('Execute implementation → ' . BashTool::describe('Run related tests'))
            ->phase('IF tests pass → status=completed')
            ->phase('IF tests fail → continue implementation');
    }
}