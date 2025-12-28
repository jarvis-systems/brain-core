<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Task creation specialist that analyzes user descriptions, researches context, estimates effort, and creates well-structured tasks after user approval.')]
class TaskCreateInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // Iron Rules
        $this->rule('analyze-arguments')->critical()
            ->text('MUST analyze an input task thoroughly before creating any task')
            ->why('User description requires deep understanding to create accurate task specification')
            ->onViolation('Parse and analyze an input task first, extract scope, requirements, and context');

        $this->rule('search-memory-first')->critical()
            ->text('MUST search vector memory for similar past work before analysis')
            ->why('Prevents duplicate work and leverages existing insights')
            ->onViolation('Execute ' . VectorMemoryMcp::call('search_memories', '{query: "{task_domain}", limit: 5}'));

        $this->rule('estimate-required')->critical()
            ->text('MUST provide time estimate for the task')
            ->why('Estimates enable planning and identify tasks needing decomposition')
            ->onViolation('Add estimate in hours before presenting task');

        $this->rule('mandatory-user-approval')->critical()
            ->text('MUST get explicit user approval BEFORE creating any task. EXCEPTION: If '.Store::var('HAS_Y_FLAG').' is true, auto-approve task creation (skip user confirmation prompt).')
            ->why('User must validate task specification before committing to vector storage. Flag -y enables automated/scripted execution.')
            ->onViolation('Present task specification and wait for explicit YES/APPROVE/CONFIRM (unless '.Store::var('HAS_Y_FLAG').' is true)');

        $this->rule('max-task-estimate')->high()
            ->text('If estimate >5-8 hours, MUST strongly recommend /task:decompose')
            ->why('Large tasks should be decomposed for better manageability and tracking')
            ->onViolation('Warn user and recommend decomposition after task creation');

        $this->rule('create-only-no-execution')->critical()
            ->text('This command ONLY creates tasks. NEVER execute the task after creation, regardless of size or complexity.')
            ->why('Task creation and task execution are separate concerns. User decides when to execute via /task:next or /do commands.')
            ->onViolation('STOP immediately. Return created task ID and let user decide next action.');

        $this->rule('comment-with-context')->critical()
            ->text('MUST add initial comment with useful links: memory IDs from research, relevant file paths from codebase exploration, related task IDs.')
            ->why('Comments preserve critical context for future execution. Without links, executor loses valuable research done during creation.')
            ->onViolation('Add comment with: Memory refs (IDs from PRIOR_WORK), File refs (paths from CODEBASE_CONTEXT), Related tasks (from EXISTING_TASKS).');

        $this->rule('deep-research-mandatory')->critical()
            ->text('MUST perform comprehensive research BEFORE formulating task: existing tasks, vector memory, codebase (if code-related), documentation.')
            ->why('Quality task creation requires full context. Skipping research leads to duplicate tasks, missed dependencies, and poor estimates.')
            ->onViolation('STOP. Execute ALL research steps (existing tasks, memory, codebase exploration) before proceeding to analysis.');

        $this->rule('check-existing-tasks')->critical()
            ->text('MUST search existing tasks for duplicates or related work before creating new task.')
            ->why('Prevents duplicate tasks, identifies potential parent tasks, reveals blocked/blocking relationships.')
            ->onViolation('Execute ' . VectorTaskMcp::call('task_list', '{query: "{objective}", limit: 10}') . ' and analyze results.');

        $this->rule('mandatory-agent-delegation')->critical()
            ->text('ALL research steps (existing tasks, vector memory, codebase, documentation) MUST be delegated to specialized agents. NEVER execute research directly.')
            ->why('Direct execution consumes command context. Agents have dedicated context for deep research and return concise structured reports.')
            ->onViolation('STOP. Delegate to: vector-master (tasks/memory), explore (codebase), documentation-master (docs). Never use direct MCP/Glob/Grep calls for research.');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('TASK_DESCRIPTION', '{task description extracted from '.Store::var('RAW_INPUT').'}'));

        // Role definition
        $this->guideline('role')
            ->text('Task creation specialist that analyzes user descriptions, researches context, estimates effort, and creates well-structured tasks after user approval.');

        // Workflow Step 0 - Parse Arguments
        $this->guideline('workflow-step0')
            ->text('STEP 0 - Parse an input task and Understand')
            ->example()
            ->phase(Store::as('HAS_Y_FLAG', '{true if '.Store::var('RAW_INPUT').' contains "-y" or "--yes"}'))
            ->phase('parse', Operator::task(Store::get('TASK_DESCRIPTION')))
            ->phase('action-1', 'Extract: primary objective, scope, requirements from user description')
            ->phase('action-2', 'Identify: implicit constraints, technical domain, affected areas')
            ->phase('action-3', 'Determine: task type (feature, bugfix, refactor, research, docs)')
            ->phase()->name('output')->do(
                Store::as('TASK_SCOPE', 'parsed objective, domain, requirements, type'),
                Store::as('TASK_TEXT', 'full original user description from ' . Store::var('TASK_DESCRIPTION'))
            );

        // Workflow Step 1 - Search Existing Tasks (MANDATORY - DELEGATED)
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Search Existing Tasks for Duplicates/Related Work (MANDATORY)')
            ->example()
            ->phase('delegate', TaskTool::agent(
                'vector-master',
                'Search existing tasks for potential duplicates or related work. ' .
                'Task objective: {' . Store::get('TASK_SCOPE') . '}. ' .
                'Search by: 1) objective keywords, 2) domain terms, 3) pending tasks. ' .
                'Analyze: duplicates, potential parent tasks, dependencies (blocked-by, blocks). ' .
                'Return: structured report with task IDs, relationships, recommendation (create new / update existing / make subtask).'
            ))
            ->phase('decision', Operator::if(
                'duplicate task found in agent report',
                'STOP. Inform user about existing task ID and ask: update existing or create new?',
                'Continue to next step'
            ))
            ->phase('output', Store::as('EXISTING_TASKS', 'agent report: related task IDs, potential parent, dependencies'));

        // Workflow Step 2 - Search Vector Memory (MANDATORY - DELEGATED)
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Deep Search Vector Memory for Prior Knowledge (MANDATORY)')
            ->example()
            ->phase('delegate', TaskTool::agent(
                'vector-master',
                'Deep multi-probe search for prior knowledge related to task. ' .
                'Task context: {' . Store::get('TASK_SCOPE') . '}. ' .
                'Search categories: code-solution, architecture, bug-fix, learning. ' .
                'Use decomposed queries: 1) domain + objective, 2) implementation patterns, 3) known bugs/errors, 4) lessons learned. ' .
                'Return: structured report with memory IDs, key insights, reusable patterns, approaches to avoid, past mistakes.'
            ))
            ->phase('output', Store::as('PRIOR_WORK', 'agent report: memory IDs, insights, recommendations, warnings'));

        // Workflow Step 3 - Codebase Exploration (for code-related tasks)
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Codebase Exploration (MANDATORY for code-related tasks)')
            ->example()
            ->phase('decision', Operator::if(
                'task is code-related (feature, bugfix, refactor)',
                Operator::task(
                    TaskTool::agent('explore', 'Comprehensive scan for {domain}. Find: existing implementations, related components, patterns used, dependencies, test coverage. Return: relevant files with paths, architecture notes, integration points'),
                    'Wait for Explore agent to complete'
                ),
                Operator::skip('Task is not code-related (research, docs)')
            ))
            ->phase('output', Store::as('CODEBASE_CONTEXT', 'relevant files, patterns, dependencies, integration points'));

        // Workflow Step 4 - Documentation Research (DELEGATED)
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Documentation Research (if relevant)')
            ->example()
            ->phase('decision', Operator::if(
                'task involves architecture, API, or external integrations',
                TaskTool::agent(
                    'documentation-master',
                    'Research documentation for task context. ' .
                    'Domain: {' . Store::get('TASK_SCOPE') . '}. ' .
                    'Search: 1) project .docs/ via brain docs command, 2) relevant package docs if external deps. ' .
                    'Return: structured report with doc paths, API specs, architectural decisions, relevant sections.'
                ),
                Operator::skip('Documentation scan not needed for this task type')
            ))
            ->phase('output', Store::as('DOC_CONTEXT', 'agent report: documentation references, API specs, architectural decisions'));

        // Workflow Step 5 - Deep Analysis
        $this->guideline('workflow-step5')
            ->text('STEP 5 - Task Analysis via Sequential Thinking')
            ->example()
            ->phase('thinking', SequentialThinkingMcp::call('sequentialthinking', '{
                    thought: "Analyzing task scope, complexity, and requirements for: ' . Store::get('TASK_SCOPE') . '",
                    thoughtNumber: 1,
                    totalThoughts: 4,
                    nextThoughtNeeded: true
                }'))
            ->phase('analyze-1', 'Assess complexity: simple (1-2h), moderate (2-4h), complex (4-6h), major (6-8h), decompose (>8h)')
            ->phase('analyze-2', 'Identify: dependencies, blockers, prerequisites from ' . Store::get('EXISTING_TASKS') . ' and ' . Store::get('CODEBASE_CONTEXT'))
            ->phase('analyze-3', 'Determine: priority based on urgency and impact')
            ->phase('analyze-4', 'Extract: acceptance criteria from requirements')
            ->phase('output', Store::as('ANALYSIS', 'complexity, estimate, priority, dependencies, criteria'));

        // Workflow Step 6 - Formulate Task Specification
        $this->guideline('workflow-step6')
            ->text('STEP 6 - Formulate Task Specification with Context Links')
            ->example()
            ->phase('title', 'Create concise title (max 10 words) capturing objective')
            ->phase('content', 'Write detailed description with: objective, context, acceptance criteria, implementation hints')
            ->phase('priority', 'Assign: critical | high | medium | low')
            ->phase('tags', 'Add relevant tags: [category, domain, stack]')
            ->phase('estimate', 'Set time estimate in hours')
            ->phase('comment', Operator::do(
                'Build initial comment with research context:',
                '- Memory refs: list memory IDs from ' . Store::get('PRIOR_WORK') . ' (format: "Related memories: #ID1, #ID2")',
                '- File refs: list key file paths from ' . Store::get('CODEBASE_CONTEXT') . ' (format: "Key files: path1, path2")',
                '- Task refs: list related task IDs from ' . Store::get('EXISTING_TASKS') . ' (format: "Related tasks: #ID1, #ID2")',
                '- Doc refs: list doc paths from ' . Store::get('DOC_CONTEXT') . ' if available'
            ))
            ->phase('output', Store::as('TASK_SPEC', '{title, content, priority, tags, estimate, comment}'));

        // Workflow Step 7 - Present for Approval (MANDATORY)
        $this->guideline('workflow-step7')
            ->text('STEP 7 - Present Task for User Approval (MANDATORY GATE)')
            ->example()
            ->phase('present-1', 'Display task specification:')
            ->phase('present-2', '  Title: {title}')
            ->phase('present-3', '  Priority: {priority}')
            ->phase('present-4', '  Estimate: {estimate} hours')
            ->phase('present-5', '  Tags: {tags}')
            ->phase('present-6', '  Content: {content preview}')
            ->phase('present-7', '  Related Tasks: ' . Store::get('EXISTING_TASKS'))
            ->phase('present-8', '  Prior Work: Memory IDs from ' . Store::get('PRIOR_WORK'))
            ->phase('present-9', '  Codebase Context: ' . Store::get('CODEBASE_CONTEXT'))
            ->phase('warning', Operator::if(
                'estimate > 8 hours',
                'WARN: Estimate exceeds 8h. Strongly recommend running /task:decompose {task_id} after creation.'
            ))
            ->phase('prompt', 'Ask: "Create this task? (yes/no/modify)"')
            ->phase('gate', Operator::validate(
                'User response is YES, APPROVE, CONFIRM, or Y',
                'Wait for explicit approval. Allow modifications if requested.'
            ));

        // Workflow Step 8 - Create Task with Context Comment
        $this->guideline('workflow-step8')
            ->text('STEP 8 - Create Task After Approval (with context links in comment)')
            ->example()
            ->phase('create', VectorTaskMcp::call('task_create', '{
                    title: "' . Store::get('TASK_SPEC') . '.title",
                    content: "' . Store::get('TASK_SPEC') . '.content",
                    priority: "' . Store::get('TASK_SPEC') . '.priority",
                    tags: ' . Store::get('TASK_SPEC') . '.tags,
                    comment: "' . Store::get('TASK_SPEC') . '.comment"
                }'))
            ->phase('capture', Store::as('CREATED_TASK_ID', 'task ID from response'));

        // Workflow Step 9 - Post-Creation Actions
        $this->guideline('workflow-step9')
            ->text('STEP 9 - Post-Creation Summary (END - NO EXECUTION)')
            ->example()
            ->phase('confirm', 'Report: Task created with ID: ' . Store::get('CREATED_TASK_ID'))
            ->phase('decompose-check', Operator::if(
                'estimate > 8 hours',
                'STRONGLY RECOMMEND: Run /task:decompose ' . Store::get('CREATED_TASK_ID') . ' to break down this large task'
            ))
            ->phase('next-steps', 'Suggest: /task:next to start working, /task:list to view all tasks')
            ->phase('stop', 'STOP HERE. Do NOT execute the task. Return control to user.');

        // Workflow Step 10 - Store Insight
        $this->guideline('workflow-step10')
            ->text('STEP 10 - Store Task Creation Insight')
            ->example()
            ->phase('store', VectorMemoryMcp::call('store_memory', '{
                    content: "Created task: {title}. Domain: {domain}. Approach: {key insights from analysis}. Estimate: {hours}h.",
                    category: "tool-usage",
                    tags: ["task-creation", "{domain}"]
                }'));

        // Task Specification Format
        $this->guideline('task-format')
            ->text('Required task specification structure')
            ->example('Concise, action-oriented (max 10 words)')->key('title')
            ->example('Detailed with: objective, context, acceptance criteria, hints')->key('content')
            ->example('critical | high | medium | low')->key('priority')
            ->example('[category, domain, stack-tags]')->key('tags')
            ->example('1-8 hours (>8h needs decomposition)')->key('estimate');

        // Estimation Guidelines
        $this->guideline('estimation-rules')
            ->text('Task estimation guidelines')
            ->example('1-2h: Config changes, simple edits, minor fixes')->key('xs')
            ->example('2-4h: Small features, multi-file changes, tests')->key('s')
            ->example('4-6h: Moderate features, refactoring, integrations')->key('m')
            ->example('6-8h: Complex features, architectural changes')->key('l')
            ->example('>8h: MUST recommend /task:decompose')->key('xl');

        // Priority Assignment
        $this->guideline('priority-rules')
            ->text('Priority assignment criteria')
            ->example('Blockers, security issues, data integrity, production bugs')->key('critical')
            ->example('Key features, deadlines, dependencies for other work')->key('high')
            ->example('Standard features, improvements, optimizations')->key('medium')
            ->example('Nice-to-have, cosmetic, documentation, cleanup')->key('low');

        // Quality Gates
        $this->guideline('quality-gates')
            ->text('ALL checkpoints MUST pass before task creation')
            ->example('Step 0: ' . Store::get('TASK_TEXT') . ' fully parsed - objective, domain, type extracted')
            ->example('Step 1: Existing tasks searched - duplicates checked, dependencies identified')
            ->example('Step 2: Vector memory searched - code-solution, architecture, bug-fix, learning categories')
            ->example('Step 3: Codebase explored (if code-related) - relevant files, patterns, dependencies found')
            ->example('Step 4: Documentation reviewed (if architecture/API) - specs, decisions documented')
            ->example('Step 5: Sequential thinking analysis completed - complexity, estimate, priority determined')
            ->example('Step 6: Task spec complete - title, content, priority, tags, estimate, comment with context links')
            ->example('Step 7: User approval explicitly received - YES/APPROVE/CONFIRM')
            ->example('Step 8: Task created with comment containing memory IDs, file paths, related task IDs')
            ->example('Step 9: STOP after creation - do NOT execute task');

        // Comment Format Guidelines
        $this->guideline('comment-format')
            ->text('Initial task comment structure for context preservation')
            ->example('Related memories: #42, #58, #73 (insights about {domain})')->key('memory-refs')
            ->example('Key files: src/Services/Auth.php:45, app/Models/User.php')->key('file-refs')
            ->example('Related tasks: #12 (blocked-by), #15 (related)')->key('task-refs')
            ->example('Docs: .docs/architecture/auth-flow.md')->key('doc-refs')
            ->example('Notes: {any critical insights from research}')->key('notes');
    }
}
