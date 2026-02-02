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
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Async vector task execution via agent delegation. Brain orchestrates with critical thinking, agents execute. Researches when ambiguous, adapts examples. Parallel when independent.')]
class TaskAsyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('task-get-first')->critical()->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze what to delegate.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('no-verbose')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action.');
        $this->rule('show-progress')->high()->text('ALWAYS show brief step status and results. User must see what is happening and can interrupt/correct at any moment.');

        // CRITICAL THINKING FOR DELEGATION
        $this->rule('smart-delegation')->critical()->text('Brain must understand task INTENT before delegating. Agents execute, but Brain decides WHAT to delegate and HOW to split work.');
        $this->rule('research-triggers')->critical()->text('Research BEFORE delegation when ANY: 1) content <50 chars, 2) contains "example/like/similar/e.g./такий як", 3) no file paths AND no class/function names, 4) references unknown library/pattern, 5) contradicts existing code, 6) multiple valid interpretations, 7) task asks "how to" without specifics.');
        $this->rule('research-flow')->high()->text('Research order: 1) context7 for library docs, 2) web-research-master for patterns. -y flag: auto-select best approach for delegation. No -y: present options to user.');

        // ASYNC EXECUTION RULES
        $this->rule('never-execute-directly')->critical()->text('Brain NEVER calls Edit/Write/Glob/Grep/Read for implementation. ALL work via Task() to agents.');
        $this->rule('atomic-tasks')->critical()->text('Each agent task: 1-2 files (max 3-5 if same feature). NO broad changes.');
        $this->rule('parallel-when-safe')->high()->text('Parallel: independent tasks, different files, no data flow. Multiple Task() in ONE message.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task (ALWAYS FIRST)
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))
            ->phase(Operator::if('status=completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('status=in_progress', 'SESSION RECOVERY: check if crashed session → continue', Operator::abort('another session active')))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' for broader context'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('SUBTASKS'))

            // 2. Fast-path check (simple tasks skip research)
            ->phase(Store::as('IS_SIMPLE', 'task.content >=50 chars AND has specific file/class/function AND no "example/like/similar" AND single clear interpretation'))
            ->phase(Operator::if(Store::get('IS_SIMPLE'), 'SKIP to step 4 (Context gathering)'))

            // 3. Research (ONLY if triggers matched)
            ->phase(Store::as('NEEDS_RESEARCH', 'ANY: content <50 chars, contains "example/like/similar/e.g./такий як/як у", no paths AND no class names, unknown lib/pattern, contradicts code, ambiguous, "how to" without specifics'))
            ->phase(Operator::if(Store::get('NEEDS_RESEARCH'), [
                '3.1: ' . Context7Mcp::call('resolve-library-id', '{libraryName: "{detected_lib}"}') . ' → IF library mentioned',
                '3.2: ' . Context7Mcp::call('query-docs', '{query: "{task question}"}') . ' → get docs',
                '3.3: IF context7 insufficient → ' . TaskTool::agent('web-research-master', 'Research: {task.title}. Find: implementation patterns, best practices.'),
                Store::as('RESEARCH_OPTIONS', '[{option, source, pros, cons}]'),
            ]))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND $HAS_AUTO_APPROVE', 'Auto-select BEST approach for delegation'))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND NOT $HAS_AUTO_APPROVE', 'Present: "Found N approaches: 1)... 2)... Which? (or your variant)"'))

            // 4. Context gathering (memory + local docs)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' → ' . Store::as('MEMORY'))
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 3}') . ' → ' . Store::as('RELATED'))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords}')) . ' → ' . Store::as('DOCS'))
            ->phase(Operator::if('docs found', TaskTool::agent('explore', 'Read docs: {doc.paths}')))

            // 5. Plan & Approval
            ->phase('Analyze task INTENT → break into atomic agent subtasks')
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Planning delegation: 1) What is the INTENT? 2) Which agents? 3) Parallel or sequential? 4) File scope per agent?",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase(Store::as('PLAN', '[{agent, subtask, files, parallel: bool, order}]'))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'execute immediately', 'show plan, wait "yes"'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Started", append_comment: true}'))

            // 6. Execute via agents
            ->phase('Delegate based on ' . Store::get('PLAN'))
            ->phase('Independent → multiple ' . TaskTool::agent('{agent}', '{subtask}') . ' in ONE message (parallel)')
            ->phase('Dependent → sequential delegation')

            // 7. Complete
            ->phase('Collect agent results')
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Done. Files: {list}", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}, learnings", category: "code-solution"}'));

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
            ->phase(Operator::if('all tests pass', VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "TDD: Tests PASSED", append_comment: true}')))
            ->phase(Operator::if('tests fail', 'continue implementation via agents, do NOT mark completed'));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('task already completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('research triggers matched but context7 empty AND web-research empty', 'Ask user for clarification'))
            ->phase(Operator::if('multiple research options, user chose "other"', 'Ask for details, incorporate into plan'))
            ->phase(Operator::if('agent fails', 'retry with different agent OR escalate to user'))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild, re-present'));

        // Agent memory pattern
        $this->guideline('agent-memory')
            ->text('ALL agent delegations MUST include memory instruction:')
            ->text('"Search memory for: {relevant_terms}. Store learnings after completion."')
            ->text('Memory = agent communication channel across sessions');
    }
}