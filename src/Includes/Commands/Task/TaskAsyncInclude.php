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

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}'))
            ->phase('IF not found → ABORT')
            ->phase('IF status=in_progress with status_history.to=null → SESSION RECOVERY')
            ->phase('IF status=completed → ask "Re-execute?"')
            ->phase('IF parent_id → load parent context')

            // 2. Discovery
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "$TASK", limit: 5}'))
            ->phase(BashTool::call(BrainCLI::LIST_MASTERS))
            ->phase('Match task to agents, determine: scan_targets, web_research, docs_scan')
            ->phase('IF complex → APPROVAL #1 (skip if -y) → task_update(in_progress)')

            // 3. Gather (optional)
            ->phase('IF scan needed → ' . TaskTool::agent('explore', 'Extract context from {targets}'))
            ->phase('IF docs needed → ' . BashTool::call(BrainCLI::DOCS('{keywords}')) . ' → ' . TaskTool::agent('explore', 'Read docs'))
            ->phase('IF web needed → ' . TaskTool::agent('web-research-master', 'Research {topic}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for $TASK", category: "tool-usage"}'))

            // 4. Plan
            ->phase('Create plan: [{step, agent, task, files (≤2), memory_query}]')
            ->phase('Analyze dependencies → sequential OR parallel')
            ->phase('Show plan → APPROVAL #2 (skip if -y)')

            // 5. Execute via agents
            ->phase('SEQUENTIAL: FOR EACH step → ' . TaskTool::agent('{agent}', '{task}') . ' → wait → next')
            ->phase('PARALLEL: Multiple Task() in SINGLE message → wait ALL')
            ->phase('IF step fails → Retry/Skip/Abort')

            // 6. Complete
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "completed", comment: "Files: {list}", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, {learnings}", category: "code-solution"}'))
            ->phase('Output: task, status, steps completed, files');

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