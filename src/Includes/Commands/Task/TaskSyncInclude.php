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

#[Purpose('Direct synchronous vector task execution by Brain without agent delegation. Accepts task ID reference (formats: "15", "#15", "task 15"), loads task context, and executes directly using Read/Edit/Write/Glob/Grep tools. Single approval gate. Best for: simple tasks, quick fixes, single-file changes within vector task workflow.')]
class TaskSyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // Iron Rules - Zero Tolerance
        $this->rule('zero-distractions')->critical()
            ->text('ZERO distractions - implement ONLY specified task from vector task content. NO creative additions, NO unapproved features, NO scope creep.')
            ->why('Ensures focused execution and prevents feature drift')
            ->onViolation('Abort immediately. Return to approved plan.');

        $this->rule('no-delegation')->critical()
            ->text('Brain executes ALL steps directly. NO Task() delegation to agents. Use ONLY direct tools: Read, Edit, Write, Glob, Grep, Bash.')
            ->why('Sync mode is for direct execution without agent overhead')
            ->onViolation('Remove Task() calls. Execute directly.');

        // Common rule from trait
        $this->defineSingleApprovalGateRule();

        $this->rule('atomic-execution')->critical()
            ->text('Execute ONLY approved plan steps. NO improvisation, NO "while we\'re here" additions. Atomic changes only.')
            ->why('Maintains plan integrity and predictability')
            ->onViolation('Revert to approved plan. Resume approved steps only.');

        $this->rule('read-before-edit')->high()
            ->text('ALWAYS Read file BEFORE Edit/Write. Never modify files blindly.')
            ->why('Ensures accurate edits based on current file state')
            ->onViolation('Read file first, then proceed with edit.');

        $this->rule('vector-memory-integration')->high()
            ->text('Search vector memory BEFORE planning. Store learnings AFTER completion.')
            ->why('Leverages past solutions, builds knowledge base')
            ->onViolation('Include memory search in analysis, store insights after.');

        // Common rule from trait
        $this->defineVectorTaskIdRequiredRule('/do:sync');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        // Common guideline from trait
        $this->defineInputCaptureGuideline();

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task with full context using $VECTOR_TASK_ID from input capture')
            ->example()
            ->phase(Operator::output([
                '=== TASK:SYNC ACTIVATED ===',
                '',
                '=== PHASE 0: VECTOR TASK LOADING ===',
                'Loading task #{$VECTOR_TASK_ID}...',
            ]))
            ->phase('Use $VECTOR_TASK_ID from input capture (already parsed from $CLEAN_ARGS)')
            ->phase('Use $HAS_AUTO_APPROVE for approval gate behavior')
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK', '{task object with title, content, status, parent_id, priority, tags, comment}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status === "completed"', [
                Operator::report('Vector task #$VECTOR_TASK_ID already completed'),
                'Ask user: "Re-execute this task? (yes/no)"',
                'WAIT for user decision',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status === "tested"', [
                Operator::note('Check for TDD mode marker in comment'),
                Store::as('IS_TDD_EXECUTION', '{$VECTOR_TASK.comment contains "TDD MODE"}'),
                Operator::if('$IS_TDD_EXECUTION === true', [
                    Operator::output([
                        '',
                        '🧪 TDD EXECUTION MODE',
                        'Task #{$VECTOR_TASK_ID} has status "tested" with TDD marker.',
                        'This task has tests written but feature NOT yet implemented.',
                        'Tests are expected to FAIL initially. After implementation, tests should PASS.',
                        '',
                        'Proceeding with feature implementation...',
                    ]),
                ]),
                Operator::if('$IS_TDD_EXECUTION === false', [
                    Operator::output([
                        '',
                        '✅ TESTED TASK EXECUTION',
                        'Task #{$VECTOR_TASK_ID} has status "tested" (post-implementation validated).',
                        'Proceeding with execution. Tests should continue to PASS.',
                    ]),
                ]),
            ]))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK', '{parent task for broader context}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 20}'))
            ->phase(Store::as('SUBTASKS', '{child tasks if any}'))
            ->phase(Store::as('TASK', '$VECTOR_TASK.title + $VECTOR_TASK.content'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent: {$PARENT_TASK.title or "none"}',
                'Subtasks: {count or "none"}',
                'Comment: {$VECTOR_TASK.comment or "none"}',
            ]));

        // Phase 1: Context Analysis
        $this->guideline('phase1-context-analysis')
            ->goal('Analyze task and gather context from conversation + memory')
            ->example()
            ->phase('Analyze conversation: requirements, constraints, preferences, prior decisions')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "similar: {$TASK}", limit: 5, category: "code-solution"}'))
            ->phase(Store::as('PRIOR_SOLUTIONS', 'Relevant past approaches'))
            ->phase(Operator::output([
                '=== CONTEXT ===',
                'Task: {$TASK}',
                'Task comment insights: {from $VECTOR_TASK.comment}',
                'Prior solutions: {summary or "none found"}',
            ]));

        // Phase 1.5: Material Gathering with Vector Storage
        $this->guideline('phase1.5-material-gathering')
            ->goal('Collect materials per plan and store to vector memory. NOTE: command `brain docs` returns file index (Path, Name, Description, etc.), then Read relevant files')
            ->example()
            ->phase(Operator::forEach('scan_target in $REQUIREMENTS_PLAN.scan_targets', [
                Operator::do('Context extraction from {scan_target}'),
                Store::as('GATHERED_MATERIALS[{target}]', 'Extracted context'),
            ]))
            ->phase(Operator::if('$DOCS_SCAN_NEEDED === true', [
                BashTool::describe(BrainCLI::DOCS('{keywords}'), 'Find documentation index (returns: Path, Name, Description)'),
                Store::as('DOCS_INDEX', 'Documentation file index'),
                Operator::forEach('doc in $DOCS_INDEX', [
                    ReadTool::call('{doc.path}'),
                ]),
                Store::as('DOCS_SCAN_FINDINGS', 'Documentation content'),
            ]))
            ->phase(Operator::if('$WEB_RESEARCH_NEEDED === true', [
                WebSearchTool::describe('Research best practices for {$TASK}'),
                Store::as('WEB_RESEARCH_FINDINGS', 'External knowledge'),
            ]))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for {$TASK}\\n\\nMaterials: {summary}", category: "tool-usage", tags: ["task-sync", "context-gathering"]}'))
            ->phase(Operator::output([
                '=== PHASE 1.5: MATERIALS GATHERED ===',
                'Materials: {count} | Docs: {status} | Web: {status}',
                'Context stored to vector memory ✓',
            ]));

        // Phase 2: Exploration & Planning
        $this->guideline('phase2-exploration-planning')
            ->goal('Explore codebase, identify targets, create execution plan')
            ->example()
            ->phase('Identify files to examine based on task description')
            ->phase(GlobTool::describe('Find relevant files: patterns based on task'))
            ->phase(GrepTool::describe('Search for relevant code patterns'))
            ->phase(ReadTool::describe('Read identified files for context'))
            ->phase(Store::as('CONTEXT', '{files_found, code_patterns, current_state}'))
            ->phase('Create atomic execution plan: specific edits with exact changes')
            ->phase(Store::as('PLAN', '[{step_N, file, action: read|edit|write, description, exact_changes}, ...]'))
            ->phase(Operator::output([
                '',
                '=== EXECUTION PLAN ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Files: {list}',
                'Steps:',
                '{numbered_steps_with_descriptions}',
                '',
                '⚠️ APPROVAL REQUIRED',
                '✅ approved/yes | ❌ no/modifications',
            ]))
            ->phase('WAIT for user approval')
            ->phase(Operator::verify('User approved'))
            ->phase(Operator::if('rejected', 'Modify plan → Re-present → WAIT'))
            ->phase('IMMEDIATELY after approval - set task in_progress (exploration/gathering IS execution)')
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Execution started after plan approval", append_comment: true}'))
            ->phase(Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} started']));

        // Phase 3: Direct Execution
        $this->guideline('phase3-direct-execution')
            ->goal('Execute plan directly using Brain tools - no delegation')
            ->example()
            ->phase('NOTE: Task already in_progress since Phase 2 approval')
            ->phase(Operator::forEach('step in $PLAN', [
                Operator::output(['▶️ Step {N}: {step.description}']),
                Operator::if('step.action === "read"', [
                    ReadTool::call('{step.file}'),
                    Store::as('FILE_CONTENT[{N}]', 'File content'),
                ]),
                Operator::if('step.action === "edit"', [
                    ReadTool::call('{step.file}'),
                    EditTool::call('{step.file}', '{old_string}', '{new_string}'),
                ]),
                Operator::if('step.action === "write"', [
                    WriteTool::call('{step.file}', '{content}'),
                ]),
                Store::as('STEP_RESULTS[{N}]', 'Result'),
                Operator::output(['✅ Step {N} complete']),
            ]))
            ->phase(Operator::if('step fails', [
                'Log error',
                'Offer: Retry / Skip / Abort',
                'WAIT for user decision',
            ]));

        // Phase 4: Completion (with TDD test verification)
        $this->guideline('phase4-completion')
            ->goal('Report results, run tests for TDD mode, update vector task status, and store learnings to vector memory')
            ->example()
            ->phase(Store::as('SUMMARY', '{completed_steps, files_modified, outcome}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Completed task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nApproach: {steps}\\n\\nFiles: {list}\\n\\nLearnings: {insights}", category: "code-solution", tags: ["task:sync", "completed"]}'))
            ->phase(Operator::if('$IS_TDD_EXECUTION === true AND status === SUCCESS', [
                Operator::output([
                    '',
                    '🧪 TDD: Running tests to verify implementation...',
                ]),
                BashTool::describe('Run tests related to task', 'php artisan test --filter="{related_test_pattern}" OR vendor/bin/pest --filter="{pattern}"'),
                Store::as('TDD_TEST_RESULTS', '{test execution results}'),
                Operator::if('$TDD_TEST_RESULTS === ALL_PASS', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "TDD Implementation completed. Tests PASSED.\\n\\nFiles: {list}. Memory: #{memory_id}\\n\\nTest results: {$TDD_TEST_RESULTS.summary}", append_comment: true}'),
                    Operator::output([
                        '✅ TDD SUCCESS: All tests passed!',
                        '📋 Vector task #{$VECTOR_TASK_ID} completed ✓',
                        '',
                        'Next: Run /task:test-validate #{$VECTOR_TASK_ID} for post-implementation test validation',
                    ]),
                ]),
                Operator::if('$TDD_TEST_RESULTS !== ALL_PASS', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "TDD Implementation incomplete. Tests FAILED.\\n\\nPassed: {pass_count}, Failed: {fail_count}\\n\\nFailing tests: {list}", append_comment: true}'),
                    Operator::output([
                        '⚠️ TDD: Some tests still failing',
                        'Passed: {pass_count} | Failed: {fail_count}',
                        '',
                        'Continue implementation to make all tests pass.',
                    ]),
                ]),
            ]))
            ->phase(Operator::if('$IS_TDD_EXECUTION !== true AND status === SUCCESS', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Execution completed successfully. Files: {list}. Memory: #{memory_id}", append_comment: true}'),
                Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} completed ✓']),
            ]))
            ->phase(Operator::if('status === PARTIAL', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "Partial completion: {completed}/{total} steps. Remaining: {list}", append_comment: true}'),
                Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} progress saved (partial)']),
            ]))
            ->phase(Operator::if('status === FAILED', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "stopped", comment: "Execution failed: {reason}. Completed: {completed}/{total}", append_comment: true}'),
                Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} stopped (failed)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== COMPLETE ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {SUCCESS/PARTIAL/FAILED}',
                '✓ Steps: {completed}/{total} | 📁 Files: {count}',
                '{outcomes}',
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Direct error handling without agent fallback')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'Abort command',
            ])
            ->phase()->if('vector task already completed', [
                'Report: "Vector task #{id} already has status: completed"',
                'Ask user: "Do you want to re-execute this task?"',
                'WAIT for user decision',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use /do:sync for text-based task descriptions"',
                'Abort command',
            ])
            ->phase()->if('file not found', [
                'Report: "File not found: {path}"',
                'Offer: Create new file / Specify correct path / Abort',
            ])
            ->phase()->if('edit conflict', [
                'Report: "old_string not found in file"',
                'Re-read file, adjust edit, retry',
            ])
            ->phase()->if('user rejects plan', [
                'Accept modifications',
                'Rebuild plan',
                'Re-present for approval',
            ]);

        // Examples
        $this->guideline('example-simple-fix')
            ->scenario('Simple bug fix from vector task')
            ->example()
            ->phase('input', '"task 15" where task #15 is "Fix typo in UserController.php line 42"')
            ->phase('load', 'task_get(15) → title, content, status, priority')
            ->phase('plan', '1 step: Edit UserController.php')
            ->phase('execution', 'task_update(in_progress) → Read → Edit → task_update(completed) → Done')
            ->phase('result', 'Vector task #15 completed ✓');

        $this->guideline('example-add-method')
            ->scenario('Add method from vector task')
            ->example()
            ->phase('input', '"#28" where task #28 is "Add getFullName() method to User model"')
            ->phase('load', 'task_get(28) → load parent if exists')
            ->phase('plan', '2 steps: Read User.php → Edit to add method')
            ->phase('execution', 'task_update(in_progress) → Read → Edit → task_update(completed) → Done')
            ->phase('result', 'Vector task #28 completed ✓');

        $this->guideline('example-config-change')
            ->scenario('Configuration update from vector task')
            ->example()
            ->phase('input', '"task:42" where task #42 is "Change cache driver to redis in config"')
            ->phase('load', 'task_get(42) → check subtasks')
            ->phase('plan', '2 steps: Read config/cache.php → Edit driver value')
            ->phase('execution', 'task_update(in_progress) → Read → Edit → task_update(completed) → Done')
            ->phase('result', 'Vector task #42 completed ✓');

        // When to use sync vs async
        $this->guideline('sync-vs-async')
            ->text('When to use /task:sync vs /task:async')
            ->example()
            ->phase('USE /task:sync', 'Simple vector tasks, single-file changes, quick fixes, config updates, typo fixes, adding small methods')
            ->phase('USE /task:async', 'Complex multi-file vector tasks, tasks requiring research, architecture changes, tasks benefiting from specialized agents');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | ⚠️ single approval | ▶️✅ progress | 📁 files | 📋 task ID references | Direct execution, no filler');
    }
}
