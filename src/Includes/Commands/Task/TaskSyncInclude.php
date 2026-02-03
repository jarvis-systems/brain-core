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
use BrainCore\Compilation\Tools\WriteTool;
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

        // FAILURE-AWARE EXECUTION (CRITICAL - prevents repeating same mistakes)
        $this->rule('failure-history-mandatory')->critical()
            ->text('BEFORE planning: search memory category "debugging" for KNOWN FAILURES related to this task/problem. DO NOT attempt solutions that already failed.')
            ->why('Repeating failed solutions wastes time. Memory contains "this does NOT work" knowledge.')
            ->onViolation('Search debugging memories FIRST. Block known-failed approaches.');
        $this->rule('sibling-task-check')->high()
            ->text('BEFORE execution: fetch sibling tasks (same parent_id, status=completed/stopped). Check comments for what was tried and failed.')
            ->why('Previous attempts on same problem contain valuable "what not to do" information.');
        $this->rule('escalate-stuck-problems')->high()
            ->text('If task matches pattern that failed 2+ times (from memory/sibling analysis) → DO NOT attempt same approach. Escalate: research alternatives, ask user, or delegate to web-research-master.')
            ->why('Definition of insanity: doing same thing expecting different results.');

        // SYNC EXECUTION RULES (sync = blocking, not "no agents")
        $this->rule('sync-meaning')->medium()->text('Sync = synchronous/blocking execution (vs async/background). Agent delegation IS allowed for research - keeps main context clean.');
        $this->rule('read-before-edit')->critical()->text('ALWAYS Read file BEFORE Edit/Write.');
        $this->rule('understand-then-execute')->critical()->text('Understand INTENT behind task, not just literal text. Adapt examples to actual context.');

        // AUTO-APPROVE MODE (-y flag behavior)
        $this->rule('auto-approve-autonomy')->high()
            ->text('-y flag = FULL AUTONOMY. Brain makes ALL decisions without asking. Auto: install dependencies, fix linter issues, run tests, rollback on failure, select best approach.')
            ->why('User explicitly trusts Brain to complete task end-to-end. Interruptions defeat the purpose.');
        $this->rule('interactive-mode')->high()
            ->text('NO -y flag = INTERACTIVE. Ask before: installing dependencies, major architectural decisions, multiple valid approaches, destructive operations (delete, overwrite), breaking changes.')
            ->why('User wants control over significant decisions.');

        // DEPENDENCY HANDLING (language-agnostic)
        $this->rule('dependency-detection')->high()
            ->text('Detect missing dependencies: import/require/use statements that fail, unknown classes/modules, task explicitly mentions "add/install/use {package}". Store list for installation.');
        $this->rule('dependency-install')->high()
            ->text('Install dependencies: detect package manager (composer, npm, pip, cargo, go mod, etc.) from project files. -y: auto-install. No -y: ask "Need to install {packages}. Proceed?"')
            ->why('Task cannot complete without required dependencies.');
        $this->rule('dependency-audit')->medium()
            ->text('After install: run audit if available (npm audit, composer audit, pip-audit, cargo audit). Vulnerabilities found: -y = WARN and continue, no -y = ask user.');
        $this->rule('dependency-dev-vs-prod')->medium()
            ->text('Dev dependencies (test frameworks, linters, dev tools) install to dev. Production dependencies install to main. Detect from usage context.');

        // BACKUP & ROLLBACK (language-agnostic)
        $this->rule('git-safety-check')->high()
            ->text('Before multi-file changes: check git status. Uncommitted changes exist: -y = auto-stash, no -y = ask "Uncommitted changes. Stash/Commit/Abort?"')
            ->why('Protect user work from being mixed with task changes.');
        $this->rule('rollback-on-failure')->high()
            ->text('If execution fails mid-way (step N of M failed, N>1): -y = auto-rollback (git checkout changed files), no -y = ask "Rollback changes? Files: {list}"')
            ->why('Partial changes are worse than no changes.');
        $this->rule('no-git-fallback')->medium()
            ->text('No git repo: create backup files (.bak) before edit. Rollback = restore from .bak. Clean .bak files on success.');

        // SECURITY WHILE CODING (language-agnostic)
        $this->rule('security-no-secrets')->critical()
            ->text('NEVER write hardcoded secrets (passwords, API keys, tokens). Use: env variables, config files (gitignored), secret managers. If task asks to hardcode secret: REFUSE, suggest secure alternative.');
        $this->rule('security-input-validation')->high()
            ->text('Code that receives external input (user, API, file): add validation at boundaries. Validate type, format, length, allowed values. Reject/sanitize invalid input.');
        $this->rule('security-output-escaping')->high()
            ->text('Code that outputs to HTML/JS/SQL/shell: escape appropriately. HTML = htmlspecialchars/equivalent, SQL = parameterized queries, shell = escapeshellarg/equivalent.');
        $this->rule('security-parameterized-queries')->critical()
            ->text('Database queries with variables: ALWAYS parameterized/prepared statements. NEVER string concatenation. No exceptions.');

        // POST-EXECUTION VALIDATION (language-agnostic)
        $this->rule('post-exec-syntax')->critical()
            ->text('After ALL edits: verify syntax. Run language-specific check (php -l, node --check, python -m py_compile, rustc --emit=metadata, go build). Syntax error = fix immediately.');
        $this->rule('post-exec-linter')->high()
            ->text('After syntax OK: run linter if configured (eslint, phpcs, pylint, clippy, golint). Errors: -y = auto-fix if possible, no -y = show and ask. Cannot auto-fix = manual fix.');
        $this->rule('post-exec-tests')->high()
            ->text('After linter OK: run related tests. Detect test files: same directory, *Test/*_test suffix, test/ mirror structure. -y = run automatically, no -y = ask "Run tests?"')
            ->why('Code without test verification is not done.');
        $this->rule('post-exec-test-failure')->high()
            ->text('Tests fail: analyze failure, attempt fix (max 2 attempts). Still fails: -y = mark task pending with error comment, no -y = ask user for guidance.');

        // PARTIAL FAILURE HANDLING
        $this->rule('partial-failure-tracking')->high()
            ->text('Track execution state: {completed_steps: [], current_step: N, total_steps: M, changed_files: []}. Persist in task comment for recovery.');
        $this->rule('partial-failure-decision')->high()
            ->text('Step fails after previous steps changed files: 1) Attempt fix (max 2), 2) If unfixable AND -y: rollback all + mark pending, 3) If unfixable AND no -y: ask "Rollback/Skip/Manual fix?"');
        $this->rule('partial-success-option')->medium()
            ->text('If 80%+ steps succeeded and remaining are non-critical: -y = complete with warning comment, no -y = ask "Complete partial or rollback?"');

        // RETRY & TIMEOUT LIMITS
        $this->rule('retry-limit')->high()
            ->text('Edit conflict: max 3 retries. File locked: wait 2s, retry, max 5 attempts. Network error: retry with backoff, max 3. After max: fail step.');
        $this->rule('timeout-limits')->medium()
            ->text('Long operations: dependency install 120s, test suite 300s, linter 60s. Timeout exceeded: -y = skip with warning, no -y = ask "Wait/Skip/Abort?"');

        // SESSION RECOVERY (detailed)
        $this->rule('session-recovery-detection')->high()
            ->text('Task status=in_progress: check task.comment for execution state. Has completed_steps AND recent timestamp (<1h): crashed session. No state OR old timestamp (>1h): stale session.');
        $this->rule('session-recovery-action')->high()
            ->text('Crashed session: -y = continue from last completed step, no -y = ask "Continue from step N or restart?" Stale session: reset to pending, start fresh.');

        // SUBTASKS HANDLING
        $this->rule('subtasks-before-parent')->high()
            ->text('Parent task with pending subtasks: complete subtasks FIRST. Order by: priority > order field > creation date. -y = execute sequentially, no -y = show list and ask.');
        $this->rule('subtasks-parallel-option')->medium()
            ->text('Independent subtasks (no dependencies): -y = execute in parallel if possible, no -y = ask "Execute N subtasks in parallel?"');

        // BREAKING CHANGES
        $this->rule('breaking-change-detection')->high()
            ->text('Detect breaking changes: public method signature change, removed public API, changed return type, renamed exported symbol. Flag for review.');
        $this->rule('breaking-change-action')->high()
            ->text('Breaking change detected: -y = proceed with deprecation notice in comment + update callers if found, no -y = ask "This is breaking change. Proceed/Modify/Abort?"');

        // FAILURE LEARNING
        $this->rule('failure-memory')->medium()
            ->text('On task failure: store to memory with category "debugging". Content: task summary, failure reason, attempted fixes, final state. Learnings help future similar tasks.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task (ALWAYS FIRST)
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase(Operator::if('status=completed', [
                Operator::if('$HAS_AUTO_APPROVE', Operator::abort('Already completed. Use different task ID.')),
                'ask "Re-execute completed task?"',
            ]))
            ->phase(Operator::if('status=in_progress', [
                'Parse task.comment for execution_state JSON',
                Operator::if('has completed_steps AND timestamp <1h', [
                    Store::as('IS_CRASHED_SESSION', 'true'),
                    Operator::if('$HAS_AUTO_APPROVE', 'Continue from last completed step'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Crashed session. Continue from step N or restart?"'),
                ]),
                Operator::if('no state OR timestamp >1h', [
                    'Stale session detected',
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Stale session reset", append_comment: true}'),
                ]),
            ]))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode → jump to tdd-mode guideline'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' ' . Store::as('PARENT') . ' (READ-ONLY context)'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' ' . Store::as('SUBTASKS'))

            // 1.5 Subtasks check
            ->phase(Operator::if(Store::get('SUBTASKS') . ' has pending items', [
                Store::as('PENDING_SUBTASKS', 'filter SUBTASKS where status=pending, order by priority,order,created_at'),
                Operator::if('$HAS_AUTO_APPROVE', 'Execute subtasks sequentially before parent'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Has N pending subtasks. Execute them first?"'),
            ]))

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

            // 4. Context gathering (memory + local docs + FAILURES)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' → past solutions')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{task.title} {problem keywords} failed error not working broken", limit: 5}') . ' ' . Store::as('KNOWN_FAILURES') . ' ← CRITICAL: what already FAILED (search by failure keywords, not category)')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 3}') . ' → related tasks')
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                [
                    VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' ' . Store::as('SIBLING_TASKS'),
                    // CRITICAL: Search vector memory for EACH sibling task to get stored insights/failures
                    Operator::forEach('sibling in ' . Store::get('SIBLING_TASKS'), [
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title}", limit: 3}') . ' → ALL memories for this sibling (failures, solutions, insights)',
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title} failed error not working", limit: 3}') . ' → specifically failure-related memories',
                        'Append results to ' . Store::as('SIBLING_MEMORIES'),
                    ]),
                    'Extract from siblings comments + ' . Store::get('SIBLING_MEMORIES') . ': what was tried, what failed, what worked',
                    Store::as('FAILURE_PATTERNS', 'solutions that were tried and failed (from sibling comments + sibling memories)'),
                ]
            ))
            ->phase(Operator::if(
                Store::get('KNOWN_FAILURES') . ' OR ' . Store::get('FAILURE_PATTERNS') . ' not empty',
                [
                    'BLOCKED APPROACHES: ' . Store::get('KNOWN_FAILURES') . ' + ' . Store::get('FAILURE_PATTERNS'),
                    'If planned solution matches blocked approach → STOP, research alternative or escalate',
                ]
            ))
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

            // 5.5 Dependency check & install
            ->phase(Operator::if('PLAN requires new dependencies', [
                Store::as('DEPS_NEEDED', '[{package, version?, dev?}]'),
                'Detect package manager from project (composer.json, package.json, requirements.txt, Cargo.toml, go.mod, etc.)',
                Operator::if('$HAS_AUTO_APPROVE', [
                    'Auto-install: run package manager install command',
                    'Run audit if available, WARN on vulnerabilities',
                ]),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Need to install: {packages}. Proceed?"'),
            ]))

            // 5.6 Git safety before changes
            ->phase(BashTool::call('git status --porcelain 2>/dev/null || echo "NO_GIT"') . ' ' . Store::as('GIT_STATUS'))
            ->phase(Operator::if(Store::get('GIT_STATUS') . ' has uncommitted changes', [
                Operator::if('$HAS_AUTO_APPROVE', BashTool::call('git stash push -m "brain-task-{id}-backup"')),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Uncommitted changes detected. Stash/Commit WIP/Abort?"'),
            ]))
            ->phase(Store::as('CHANGED_FILES', '[]'))

            // 6. Execute with tracking
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Executing...", append_comment: true}'))
            ->phase(Operator::forEach('step in ' . Store::get('PLAN'), [
                Store::as('CURRENT_STEP', '{step_index}'),
                ReadTool::call('{step.file}'),
                EditTool::call('{step.file}', '{old}', '{new}') . ' OR ' . WriteTool::call('{step.file}', '{content}'),
                'Append {step.file} to ' . Store::get('CHANGED_FILES'),
                Operator::if('step fails', [
                    'Retry up to 2 times with adjusted approach',
                    Operator::if('still fails', [
                        Operator::if('$HAS_AUTO_APPROVE AND previous steps changed files', [
                            BashTool::call('git checkout -- {changed_files}') . ' OR restore from .bak',
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Failed at step N: {error}. Rolled back.", append_comment: true}'),
                            VectorMemoryMcp::call('store_memory', '{content: "FAILURE: Task #{id}, step {N}, error: {msg}, attempted: {fixes}", category: "debugging"}'),
                            Operator::abort('Step failed, rolled back'),
                        ]),
                        Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Step N failed: {error}. Retry/Skip/Rollback/Abort?"'),
                    ]),
                ]),
                'Update task.comment with execution_state JSON for recovery',
            ]))

            // 7. Post-execution validation
            ->phase('7.1 SYNTAX CHECK: Run language-specific syntax validator on ' . Store::get('CHANGED_FILES'))
            ->phase(Operator::if('syntax errors', [
                'Attempt auto-fix (max 2 tries)',
                Operator::if('still errors', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Rollback + mark pending'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'Show errors, ask for guidance'),
                ]),
            ]))
            ->phase('7.2 LINTER: Run project linter if configured')
            ->phase(Operator::if('linter errors', [
                Operator::if('$HAS_AUTO_APPROVE', 'Auto-fix if possible (--fix flag)'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'Show issues, ask "Auto-fix/Manual/Ignore?"'),
                Operator::if('cannot auto-fix critical errors', 'Fix manually or rollback'),
            ]))
            ->phase('7.3 TESTS: Detect related test files for ' . Store::get('CHANGED_FILES'))
            ->phase(Store::as('RELATED_TESTS', 'test files in same dir, *Test suffix, test/ mirror'))
            ->phase(Operator::if(Store::get('RELATED_TESTS') . ' exist', [
                Operator::if('$HAS_AUTO_APPROVE', 'Run tests automatically'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Run related tests? Files: {list}"'),
                Operator::if('tests fail', [
                    'Analyze failure, attempt fix (max 2 tries)',
                    Operator::if('still fails', [
                        Operator::if('$HAS_AUTO_APPROVE', [
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Tests failing: {failures}", append_comment: true}'),
                            Operator::abort('Tests fail, task marked pending'),
                        ]),
                        Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Tests fail. Fix/Skip/Rollback?"'),
                    ]),
                ]),
            ]))

            // 8. Complete
            ->phase(Operator::if(Store::get('GIT_STATUS') . ' had stash', BashTool::call('git stash pop') . ' (restore user changes)'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Done. Files: {changed_files}. Tests: {pass/skip/none}.", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}, patterns used, learnings", category: "code-solution"}'));

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase(Operator::if('task.comment contains "TDD MODE" AND status=tested', 'Execute implementation based on task.content'))
            ->phase('Implement feature following existing code patterns')
            ->phase('Run tests: detect test framework from project (jest, pytest, phpunit, pest, cargo test, go test, etc.)')
            ->phase(Operator::if('all tests pass', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "TDD: All tests PASSED", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "TDD success: {feature}, implementation approach: {summary}", category: "code-solution"}'),
            ]))
            ->phase(Operator::if('tests fail', [
                'Analyze failure: assertion error vs exception vs timeout',
                'Implement fix based on test expectation',
                'Re-run tests (max 5 iterations)',
                Operator::if('still failing after 5 iterations', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "TDD stuck: {failing_tests}. Need guidance.", append_comment: true}'),
                    Operator::if('$HAS_AUTO_APPROVE', Operator::abort('TDD: Cannot pass tests after 5 iterations')),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Cannot pass tests. Show failures for manual review?"'),
                ]),
            ]));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list or task_create')))
            ->phase(Operator::if('task already completed AND -y', Operator::abort('Already completed')))
            ->phase(Operator::if('task already completed AND no -y', 'ask "Re-execute completed task?"'))
            ->phase(Operator::if('research triggers matched but context7 empty AND web-research empty', [
                Operator::if('$HAS_AUTO_APPROVE', 'Proceed with best-effort based on existing codebase patterns'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'Ask user for clarification with specific questions'),
            ]))
            ->phase(Operator::if('multiple research options, user chose "other"', 'Ask for details, incorporate into plan'))
            ->phase(Operator::if('file not found for edit', [
                Operator::if('$HAS_AUTO_APPROVE AND file should exist', Operator::abort('Expected file not found: {path}')),
                Operator::if('$HAS_AUTO_APPROVE AND new file needed', 'Create file with Write'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "File not found. Create/Specify path/Abort?"'),
            ]))
            ->phase(Operator::if('edit conflict (old_string not found)', [
                'Re-read file to get current content',
                'Adjust old_string to match current state',
                'Retry edit (max 3 attempts)',
                Operator::if('3 failures', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Use Write to replace entire file if safe'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Cannot edit. Show diff for manual resolution?"'),
                ]),
            ]))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild plan, re-present'))
            ->phase(Operator::if('dependency install fails', [
                'Check: network, permissions, version conflicts',
                Operator::if('$HAS_AUTO_APPROVE', VectorTaskMcp::call('task_update', '{status: "pending", comment: "Dependency install failed: {error}"}') . ' + abort'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Install failed: {error}. Retry/Skip dependency/Abort?"'),
            ]))
            ->phase(Operator::if('syntax check fails after edit', [
                'Parse error message, identify line/column',
                'Attempt fix (missing semicolon, bracket, import, etc.)',
                'Re-check (max 2 attempts)',
                Operator::if('still fails', 'Rollback file, report syntax error'),
            ]))
            ->phase(Operator::if('linter finds critical issues', [
                Operator::if('auto-fixable', 'Run linter --fix'),
                Operator::if('not auto-fixable', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Add TODO comment, proceed with warning'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'Show issues, ask for action'),
                ]),
            ]))
            ->phase(Operator::if('timeout on long operation', [
                Operator::if('$HAS_AUTO_APPROVE', 'Skip with warning, continue'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Operation timed out. Wait longer/Skip/Abort?"'),
            ]));
    }
}