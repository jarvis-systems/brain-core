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
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Async execution of vector task via agent delegation. Brain orchestrates, agents execute. Includes docs gathering, web research, TDD mode. Parallel when independent.')]
class TaskAsyncInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // EXECUTION DIRECTIVES
        $this->rule('execute-now')->critical()->text('This is NOT documentation. EXECUTE workflow immediately when command invoked. Do NOT ask questions, do NOT wait - START with step 1.');
        $this->rule('no-analysis')->critical()->text('NO verbose analysis, NO "let me think", NO plan output. -y flag = SILENT execution. Just delegate to agents and DO the work.');
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to execute. Task ID given = execute it. JUST DO IT.');

        // RULES
        $this->rule('never-execute-directly')->critical()->text('Brain NEVER calls Edit/Write/Glob/Grep/Read for implementation. ALL work via Task() to agents.');
        $this->rule('atomic-tasks')->critical()->text('Each agent task: 1-2 files (max 3-5 if same feature). NO broad changes.');
        $this->rule('auto-approve')->critical()->text('-y flag = NO plan output, NO approval waiting. Delegate to agents immediately.');
        $this->rule('parallel-when-safe')->high()->text('Parallel: independent tasks, different files, no data flow. Multiple Task() in ONE message.');

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}') . ' → task.content IS your work order')
            ->phase('IF not found → ABORT')
            ->phase('IF status=completed → ask "Re-execute?"')
            ->phase('IF status=in_progress → SESSION RECOVERY: check if crashed session → continue OR ABORT if another session active')
            ->phase('IF status=tested AND comment contains "TDD MODE" → TDD execution mode (tests exist, implement feature)')
            ->phase('IF parent_id → ' . VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' for broader context')
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: task_id}') . ' → load subtasks if any')

            // 2. Context gathering (memory + docs + web + related tasks)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' → past implementations, patterns')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 5}') . ' → related tasks')
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' → get documentation index')
            ->phase('IF docs found → delegate: ' . TaskTool::agent('explore', 'Read and analyze documentation files: {doc.paths}'))
            ->phase('IF web research needed → delegate: ' . TaskTool::agent('web-research-master', 'Research best practices for: {task.title}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for task: {summary}", category: "tool-usage"}'))

            // 3. Plan & Approval
            ->phase('Analyze task.content → break into atomic agent subtasks')
            ->phase(Store::as('PLAN', '[{agent, subtask, files, parallel: true/false}]'))
            ->phase('IF -y flag → skip to execution immediately')
            ->phase('ELSE → show brief plan, wait "yes"')
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "in_progress", comment: "Execution started", append_comment: true}'))

            // 4. Execute via agents
            ->phase('Delegate to agents based on PLAN:')
            ->phase('Independent subtasks → multiple ' . TaskTool::agent('{agent}', '{subtask}') . ' in ONE message (parallel)')
            ->phase('Dependent subtasks → sequential delegation')
            ->phase('Available agents: ' . BashTool::call(BrainCLI::LIST_MASTERS))

            // 5. Complete
            ->phase('Collect agent results')
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "completed", comment: "Files: {list}", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}, learnings: {insights}", category: "code-solution"}'));

        // Agent reference
        $this->guideline('agents')
            ->text('explore = code exploration, file analysis, implementation')
            ->text('web-research-master = external research, best practices')
            ->text('documentation-master = docs research, API documentation')
            ->text('commit-master = git operations, commits')
            ->text('script-master = Laravel scripts, commands')
            ->text('prompt-master = Brain component generation');

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase('IF task.comment contains "TDD MODE" AND status=tested:')
            ->phase('Execute implementation via agents based on task.content')
            ->phase('After implementation → ' . TaskTool::agent('explore', 'Run tests: php artisan test --filter="{pattern}"'))
            ->phase('IF all tests pass → ' . VectorTaskMcp::call('task_update', '{task_id, status: "completed", comment: "TDD: Tests PASSED", append_comment: true}'))
            ->phase('IF tests fail → continue implementation via agents, do NOT mark completed');

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase('IF task not found → ABORT, suggest task_list')
            ->phase('IF task already completed → ask "Re-execute?"')
            ->phase('IF agent fails → retry with different agent OR escalate to user')
            ->phase('IF user rejects plan → accept modifications, rebuild plan, re-present');

        // Agent memory pattern
        $this->guideline('agent-memory')
            ->text('ALL agent delegations MUST include memory instruction:')
            ->text('"Search memory for: {relevant_terms}. Store learnings after completion."')
            ->text('Memory = agent communication channel across sessions');
    }
}