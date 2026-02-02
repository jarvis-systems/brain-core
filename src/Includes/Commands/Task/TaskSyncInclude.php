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
use BrainCore\Compilation\Tools\TaskTool;
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Agents\WebResearchMaster;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Synchronous vector task execution by Brain. Sync = blocking execution (not background). Agent delegation allowed for research to keep context clean. Critical thinking: validates clarity, adapts examples, researches when needed.')]
class TaskSyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('task-get-first')->critical()->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze and validate.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('no-verbose')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action.');
        $this->rule('show-progress')->high()->text('ALWAYS show brief step status and results. User must see what is happening and can interrupt/correct at any moment.');

        // CRITICAL THINKING RULES
        $this->rule('fast-path')->high()->text('Simple task (clear intent, specific files, no ambiguity) → skip research, execute directly. Complex/ambiguous → full validation flow.');
        $this->rule('research-triggers')->critical()->text('Research REQUIRED when ANY: 1) content <50 chars, 2) contains "example/like/similar/e.g./такий як", 3) no file paths AND no class/function names, 4) references unknown library/pattern, 5) contradicts existing code, 6) multiple valid interpretations, 7) task asks "how to" without specifics.');
        $this->rule('research-flow')->high()->text('Research order: 1) context7 for library docs, 2) web-research-master for patterns/practices. -y flag: auto-select best. No -y: present options to user.');

        // SYNC EXECUTION RULES (sync = blocking, not "no agents")
        $this->rule('sync-meaning')->medium()->text('Sync = synchronous/blocking execution (vs async/background). Agent delegation IS allowed for research - keeps main context clean.');
        $this->rule('read-before-edit')->critical()->text('ALWAYS Read file BEFORE Edit/Write.');
        $this->rule('understand-then-execute')->critical()->text('Understand INTENT behind task, not just literal text. Adapt examples to actual context.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task (ALWAYS FIRST)
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase(Operator::if('status=completed', 'ask "Re-execute?"'))
            ->phase(Operator::if('status=in_progress', 'SESSION RECOVERY: check if crashed session', 'continue OR ' . Operator::abort('another session active')))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode (tests exist, implement feature)'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' for broader context'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' → load subtasks if any')

            // 2. Fast-path check (simple tasks skip to step 4)
            ->phase(Store::as('IS_SIMPLE', 'task.content >=50 chars AND has specific file/class/function AND no "example/like/similar" AND single clear interpretation'))
            ->phase(Operator::if(Store::get('IS_SIMPLE'), 'SKIP to step 4 (Explore & Plan)'))

            // 3. Research (ONLY if triggers matched)
            ->phase(Store::as('NEEDS_RESEARCH', 'ANY: content <50 chars, contains "example/like/similar/e.g./такий як/як у", no paths AND no class names, unknown lib/pattern, contradicts code, ambiguous, "how to" without specifics'))
            ->phase(Operator::if(Store::get('NEEDS_RESEARCH'), [
                '3.1: ' . Context7Mcp::call('resolve-library-id', '{libraryName: "{detected_lib}"}') . ' → IF library mentioned',
                '3.2: ' . Context7Mcp::call('query-docs', '{query: "{task question}"}') . ' → get docs',
                '3.3: IF context7 insufficient → ' . TaskTool::agent('web-research-master', 'Research: {task.title}. Find: implementation patterns, best practices, concrete examples.'),
                Store::as('RESEARCH_OPTIONS', '[{option, source, pros, cons}]'),
            ]))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND $HAS_AUTO_APPROVE', 'Auto-select BEST: fit with existing code > simplicity > best practices'))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND NOT $HAS_AUTO_APPROVE', 'Present: "Found N approaches: 1)... 2)... Which? (or your variant)"'))

            // 4. Context gathering (memory + local docs)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' → past solutions')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 3}') . ' → related tasks')
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords}')) . ' → project docs')
            ->phase(Operator::if('docs found', ReadTool::call('{doc.path}')))

            // 5. Explore & Plan
            ->phase(GlobTool::describe('Find relevant files'))
            ->phase(GrepTool::describe('Search existing patterns'))
            ->phase(ReadTool::describe('Read target files'))
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Planning: 1) INTENT (not literal text)? 2) Fit with existing code? 3) Minimal change? 4) Follow existing patterns?",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase(Store::as('PLAN', '[{step, file, action, changes, rationale}]'))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'execute immediately', 'show plan, wait "yes"'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Started", append_comment: true}'))

            // 6. Execute
            ->phase(Operator::forEach('step in ' . Store::get('PLAN'), [
                ReadTool::call('{step.file}'),
                EditTool::call('{step.file}', '{old}', '{new}') . ' OR ' . WriteTool::call('{step.file}', '{content}'),
            ]))
            ->phase(Operator::if('step fails', 'Retry/Skip/Abort'))

            // 7. Complete
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Done. Files: {list}", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}, learnings", category: "code-solution"}'));

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
            ->phase(Operator::if('research triggers matched but context7 empty AND web-research empty', 'Ask user for clarification with specific questions'))
            ->phase(Operator::if('multiple research options, user chose "other"', 'Ask for details, incorporate into plan'))
            ->phase(Operator::if('file not found', 'Create / Specify correct path / Abort'))
            ->phase(Operator::if('edit conflict', 'Re-read file, adjust edit, retry'))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild, re-present'));
    }
}