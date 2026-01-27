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
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('tool-call-first')->critical()->text('YOUR VERY FIRST RESPONSE MUST BE A TOOL CALL. No text before tools. No analysis. No thinking out loud. CALL mcp__vector-task__task_get IMMEDIATELY.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('execute-now')->critical()->text('This is NOT documentation. EXECUTE workflow immediately. START with step 1 NOW. Do not describe what you will do - DO IT.');
        $this->rule('no-output')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis>, "Proceed?", "let me", summaries, explanations. WITH -y FLAG: ZERO text output. ONLY tool calls.');
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content. Task ID given = execute it. JUST DO IT.');
        $this->rule('auto-approve')->critical()->text('-y flag = SILENT MODE. NO output. NO plan. NO approval. Delegate to agents immediately. Call tools only.');

        // ASYNC EXECUTION RULES
        $this->rule('never-execute-directly')->critical()->text('Brain NEVER calls Edit/Write/Glob/Grep/Read for implementation. ALL work via Task() to agents.');
        $this->rule('atomic-tasks')->critical()->text('Each agent task: 1-2 files (max 3-5 if same feature). NO broad changes.');
        $this->rule('parallel-when-safe')->high()->text('Parallel: independent tasks, different files, no data flow. Multiple Task() in ONE message.');

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $ARGUMENTS}') . ' → ' . Store::as('TASK', 'task.content IS your work order'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))
            ->phase(Operator::if('status=completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('status=in_progress', 'SESSION RECOVERY: check if crashed session → continue', Operator::abort('another session active')))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode (tests exist, implement feature)'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' for broader context'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: task_id}') . ' → ' . Store::as('SUBTASKS'))

            // 2. Context gathering (memory + docs + web + related tasks)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' → ' . Store::as('MEMORY', 'past implementations, patterns'))
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 5}') . ' → ' . Store::as('RELATED', 'related tasks'))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' → ' . Store::as('DOCS', 'documentation index'))
            ->phase(Operator::if('docs found', 'delegate: ' . TaskTool::agent('explore', 'Read and analyze documentation files: {doc.paths}')))
            ->phase(Operator::if('web research needed', 'delegate: ' . TaskTool::agent('web-research-master', 'Research best practices for: {task.title}')))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for task: {summary}", category: "tool-usage"}'))

            // 3. Plan & Approval
            ->phase('Analyze task.content → break into atomic agent subtasks')
            ->phase(Store::as('PLAN', '[{agent, subtask, files, parallel: true/false}]'))
            ->phase(Operator::if('-y flag', 'skip to execution immediately', 'show brief plan, wait "yes"'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id, status: "in_progress", comment: "Execution started", append_comment: true}'))

            // 4. Execute via agents
            ->phase('Delegate to agents based on ' . Store::get('PLAN') . ':')
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
            ->phase(Operator::if('task.comment contains "TDD MODE" AND status=tested', 'Execute implementation via agents based on task.content'))
            ->phase('After implementation → ' . TaskTool::agent('explore', 'Run tests: php artisan test --filter="{pattern}"'))
            ->phase(Operator::if('all tests pass', VectorTaskMcp::call('task_update', '{task_id, status: "completed", comment: "TDD: Tests PASSED", append_comment: true}')))
            ->phase(Operator::if('tests fail', 'continue implementation via agents, do NOT mark completed'));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('task already completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('agent fails', 'retry with different agent', 'escalate to user'))
            ->phase(Operator::if('user rejects plan', 'accept modifications, rebuild plan, re-present'));

        // Agent memory pattern
        $this->guideline('agent-memory')
            ->text('ALL agent delegations MUST include memory instruction:')
            ->text('"Search memory for: {relevant_terms}. Store learnings after completion."')
            ->text('Memory = agent communication channel across sessions');
    }
}