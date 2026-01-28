<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\EditTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\GrepTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Direct sync execution of vector task by Brain. No agent delegation. Uses Read/Edit/Write/Glob/Grep directly. Includes docs gathering via brain docs, web research, TDD mode support.')]
class TaskSyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('tool-call-first')->critical()->text('YOUR VERY FIRST RESPONSE MUST BE A TOOL CALL. No text before tools. No analysis. No thinking out loud. CALL mcp__vector-task__task_get IMMEDIATELY.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('no-verbose')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action.');
        $this->rule('show-progress')->high()->text('ALWAYS show brief step status and results. User must see what is happening and can interrupt/correct at any moment.');
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content. Task ID given = execute it. JUST DO IT.');
        $this->rule('auto-approve')->high()->text('-y flag = auto-approve. Skip "Proceed?" questions, but STILL show progress. User sees everything, just no approval prompts.');

        // SYNC EXECUTION RULES
        $this->rule('no-delegation')->critical()->text('Brain executes ALL steps directly. NO Task() delegation. Use ONLY: Read, Edit, Write, Glob, Grep, Bash.');
        $this->rule('read-before-edit')->critical()->text('ALWAYS Read file BEFORE Edit/Write.');
        $this->rule('atomic-only')->critical()->text('Execute ONLY task.content requirements. NO improvisation.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase(Operator::if('status=completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('status=in_progress', 'SESSION RECOVERY: check if crashed session', 'continue OR ' . Operator::abort('another session active')))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode (tests exist, implement feature)'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' for broader context'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' → load subtasks if any')

            // 2. Context gathering (memory + docs + web + related tasks)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' → past implementations, patterns')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 5}') . ' → related tasks')
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' → get documentation index (returns: Path, Name, Description)')
            ->phase(Operator::if('docs found', ReadTool::call('{doc.path}') . ' for each relevant doc'))
            ->phase(Operator::if('web research needed', WebSearchTool::describe('Research best practices for task')))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for task: {summary}", category: "tool-usage"}'))

            // 3. Explore & Plan
            ->phase(GlobTool::describe('Find relevant files based on task'))
            ->phase(GrepTool::describe('Search code patterns'))
            ->phase(ReadTool::describe('Read identified files'))
            ->phase(Store::as('PLAN', '[{step, file, action: read|edit|write, changes}]'))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'skip to execution immediately', 'show brief plan, wait "yes"'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Execution started", append_comment: true}'))

            // 4. Execute directly
            ->phase(Operator::forEach('step in ' . Store::get('PLAN'), [
                ReadTool::call('{step.file}'),
                EditTool::call('{step.file}', '{old}', '{new}'),
                'OR ' . WriteTool::call('{step.file}', '{content}'),
            ]))
            ->phase(Operator::if('step fails', 'Retry/Skip/Abort'))

            // 5. Complete
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Files: {list}", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}, learnings: {insights}", category: "code-solution"}'));

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase(Operator::if('task.comment contains "TDD MODE" AND status=tested', 'Execute implementation based on task.content'))
            ->phase(BashTool::describe('Run related tests', 'php artisan test --filter="{pattern}" OR vendor/bin/pest --filter="{pattern}"'))
            ->phase(Operator::if('all tests pass', VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "TDD: Tests PASSED", append_comment: true}')))
            ->phase(Operator::if('tests fail', 'continue implementation, do NOT mark completed'));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('task already completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('file not found', 'offer: Create / Specify correct path / Abort'))
            ->phase(Operator::if('edit conflict (old_string not found)', 'Re-read file, adjust edit, retry'))
            ->phase(Operator::if('user rejects plan', 'accept modifications, rebuild plan, re-present'));
    }
}