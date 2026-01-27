<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\EditTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\GrepTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WriteTool;
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

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}'))
            ->phase('IF not found → ABORT')
            ->phase('IF status=completed → ask "Re-execute?"')
            ->phase('IF parent_id → load parent context')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "$TASK", limit: 5}'))

            // 2. Explore & Plan
            ->phase(GlobTool::describe('Find relevant files'))
            ->phase(GrepTool::describe('Search code patterns'))
            ->phase(ReadTool::describe('Read identified files'))
            ->phase('Create atomic plan: [{step, file, action: read|edit|write, changes}]')
            ->phase('Show plan → WAIT approval (skip if -y)')
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "in_progress"}'))

            // 3. Execute directly
            ->phase('FOR EACH step:')
            ->phase(ReadTool::call('{file}'))
            ->phase(EditTool::call('{file}', '{old}', '{new}'))
            ->phase('OR ' . WriteTool::call('{file}', '{content}'))
            ->phase('IF step fails → Retry/Skip/Abort')

            // 4. Complete
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "completed", comment: "Files: {list}", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}", category: "code-solution"}'))
            ->phase('Output: task, status, files modified');

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase('IF task.comment contains "TDD MODE" AND status=tested:')
            ->phase('Execute implementation → ' . BashTool::describe('Run related tests'))
            ->phase('IF tests pass → status=completed')
            ->phase('IF tests fail → continue implementation');
    }
}