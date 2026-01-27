<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Async execution of vector task via agent delegation. Brain orchestrates, agents execute. Parallel when independent.')]
class TaskAsyncInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // RULES (compact)
        $this->rule('never-execute-directly')->critical()->text('Brain NEVER calls Edit/Write/Glob/Grep/Read for implementation. ALL work via Task() to agents.');
        $this->rule('atomic-tasks')->critical()->text('Each agent task: 1-2 files (max 3-5 if same feature). NO broad changes.');
        $this->rule('approval-gates')->critical()->text('2 approvals: after requirements, after plan. -y = auto-approve.');
        $this->rule('parallel-when-safe')->high()->text('Parallel: independent tasks, different files, no data flow. Multiple Task() in ONE message.');

        // WORKFLOW - ACTION instruction, not output checklist
        $this->guideline('workflow')
            ->text('1. ' . VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}') . ' → task.content IS your work order')
            ->text('2. ' . VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5}'))
            ->text('3. ' . VectorTaskMcp::call('task_update', '{task_id, status: "in_progress"}'))
            ->text('4. EXECUTE task.content by delegating to agents: ' . TaskTool::agent('{agent}', '{subtask from content}'))
            ->text('5. ' . VectorTaskMcp::call('task_update', '{task_id, status: "completed"}'))
            ->text('6. ' . VectorMemoryMcp::call('store_memory', '{content: learnings, category: "code-solution"}'));

        $this->guideline('agents')
            ->text('Available: ' . BashTool::call(BrainCLI::LIST_MASTERS))
            ->text('explore = code/files, web-research-master = web, documentation-master = docs')
            ->text('Parallel if independent files: multiple Task() in ONE message');

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase('IF task.comment contains "TDD MODE" AND status=tested:')
            ->phase('Execute via agents → ' . TaskTool::agent('explore', 'Run tests'))
            ->phase('IF tests pass → status=completed')
            ->phase('IF tests fail → continue implementation');

        // Agent memory pattern
        $this->guideline('agent-memory')->text('ALL agents: search memory BEFORE task, store learnings AFTER. Memory = agent communication channel.');
    }
}