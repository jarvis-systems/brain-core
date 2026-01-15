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
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Comprehensive vector task test validation with parallel agent orchestration. Accepts task ID reference (formats: "15", "#15", "task 15"). Validates test coverage against documentation requirements, test quality (no bloat, real workflows), test consistency, and completeness. Creates follow-up tasks for gaps. Idempotent - can be run multiple times. Best for: validating test quality of completed vector tasks.')]
class TaskTestValidateInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // ABSOLUTE FIRST - BLOCKING ENTRY RULE
        $this->rule('entry-point-blocking')->critical()
            ->text('ON RECEIVING input: Your FIRST output MUST be "=== TASK:TEST-VALIDATE ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION. FORBIDDEN first actions: Glob, Grep, Read, Edit, Write, WebSearch, WebFetch, Bash (except brain list:masters), code generation, file analysis.')
            ->why('Without explicit entry point, Brain skips workflow and executes directly. Entry point forces workflow compliance.')
            ->onViolation('STOP IMMEDIATELY. Delete any tool calls. Output "=== TASK:TEST-VALIDATE ACTIVATED ===" and restart from Phase 0.');

        // Iron Rules - Zero Tolerance
        $this->rule('test-validation-only')->critical()
            ->text('TEST VALIDATION command validates EXISTING tests. NEVER write tests directly. Only validate and CREATE TASKS for missing/broken tests.')
            ->why('Validation is read-only audit. Test writing belongs to task:async.')
            ->onViolation('Abort any test writing. Create task instead.');

        $this->rule('vector-task-id-required')->critical()
            ->text('$RAW_INPUT MUST be a vector task ID reference. Valid formats: "15", "#15", "task 15", "task:15", "task-15". If not a valid task ID, abort and suggest /do:test-validate for text-based test validation.')
            ->why('This command is exclusively for vector task test validation. Text descriptions belong to /do:test-validate.')
            ->onViolation('STOP. Report: "Invalid task ID. Use /do:test-validate for text-based validation or provide valid task ID."');

        $this->rule('testable-status-required')->critical()
            ->text('ONLY tasks with status "completed", "tested", or "validated" can be test-validated. Pending/in_progress/stopped tasks MUST first be completed via task:async.')
            ->why('Test validation audits finished work. Incomplete work cannot be test-validated.')
            ->onViolation('Report: "Task #{id} has status {status}. Complete via /task:async first."');

        $this->rule('output-status-conditional')->critical()
            ->text('Output status depends on test validation outcome: 1) PASSED + no tasks created → "tested", 2) Tasks created for fixes → "pending". NEVER set "validated" - that status is set ONLY by /task:validate command.')
            ->why('If fix tasks were created, work is NOT done - task returns to pending queue. Only when test validation passes completely (no issues, no tasks) can status be "tested".')
            ->onViolation('Check CREATED_TASKS.count: if > 0 → set "pending", if === 0 AND passed → set "tested". NEVER set "completed" or "tested" when fix tasks exist.');

        $this->rule('real-workflow-tests-only')->critical()
            ->text('Tests MUST cover REAL workflows end-to-end. Reject bloated tests that test implementation details instead of behavior. Quality over quantity.')
            ->why('Bloated tests are maintenance burden, break on refactoring, provide false confidence.')
            ->onViolation('Flag bloated tests for refactoring. Create task to simplify.');

        $this->rule('documentation-requirements-coverage')->critical()
            ->text('EVERY requirement in .docs/ MUST have corresponding test coverage. Missing coverage = immediate task creation.')
            ->why('Documentation defines expected behavior. Untested requirements are unverified.')
            ->onViolation('Create task for each uncovered requirement.');

        $this->rule('cosmetic-test-auto-fix')->critical()
            ->text('COSMETIC test issues (whitespace, indentation, extra spaces, trailing spaces, test file formatting, comment typos in tests) MUST be auto-fixed by parallel agents immediately WITHOUT creating tasks. After auto-fix, restart test validation from Phase 0.')
            ->why('Cosmetic fixes in tests are trivial, low-risk, and do not require task tracking. Immediate fix saves time and keeps task queue clean.')
            ->onViolation('Launch parallel agents to fix cosmetic test issues. DO NOT create tasks for cosmetic issues.');

        $this->rule('auto-approval-flag')->critical()
            ->text('If $RAW_INPUT contains "-y" flag, auto-approve test validation scope (skip user confirmation prompt at Phase 1).')
            ->why('Flag -y enables automated/scripted execution without manual approval.')
            ->onViolation('Check for -y flag before waiting for user approval.');

        $this->rule('parallel-agent-orchestration')->high()
            ->text('Test validation phases SHOULD scale agent orchestration to task complexity. Complex tasks may launch 5-6 agents in parallel; simple tasks can limit to 1-2 agents.')
            ->why('Parallel validation reduces time and maximizes coverage when complexity justifies it; lighter workloads stay efficient with fewer agents.')
            ->onViolation('Scale agent usage based on scope. Avoid blanket 5-6 agent blasts for simple work.');

        $this->rule('simple-test-validation-heuristic')->high()
            ->text('Detect simple vector tasks (low estimate, non-critical priority) so heavy parallel validation can be skipped.')
            ->why('Tiny tasks benefit from a low-overhead validation path that still checks docs/tests but avoids launching six agents.')
            ->onViolation('Treat mismarked simple tasks as complex before rerunning validation.');

        $this->rule('idempotent-validation')->high()
            ->text('Test validation is IDEMPOTENT. Running multiple times produces same result (no duplicate tasks, no repeated analysis).')
            ->why('Allows safe re-runs without side effects.')
            ->onViolation('Check existing tasks before creating. Skip duplicates.');

        $this->rule('session-recovery-via-history')->high()
            ->text('If task status is "in_progress", check status_history. If last entry has "to: null" - previous session crashed mid-execution. Can continue test validation WITHOUT changing status. Treat any vector memory findings from crashed session with caution - previous context is lost.')
            ->why('Prevents blocking on crashed sessions. Allows recovery while maintaining awareness that previous session context is incomplete.')
            ->onViolation('Check status_history before blocking. If to:null found, proceed with caution warning.');

        $this->rule('vector-memory-mandatory')->high()
            ->text('ALL test validation results MUST be stored to vector memory. Search memory BEFORE creating duplicate tasks.')
            ->why('Memory prevents duplicate work and provides audit trail.')
            ->onViolation('Store validation summary with findings and created tasks.');

        // CRITICAL: Fix task parent_id assignment
        $this->rule('fix-task-parent-is-validated-task')->critical()
            ->text('Fix tasks MUST have parent_id = VECTOR_TASK_ID (the task being test-validated NOW). NEVER use VECTOR_TASK.parent_id or PARENT_TASK_CONTEXT.')
            ->why('Hierarchical integrity: test validation creates subtasks of the validated task.')
            ->onViolation('VERIFY parent_id = $TASK_PARENT_ID = $VECTOR_TASK_ID before task_create. If wrong, ABORT and recalculate.');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('VECTOR_TASK_ID', '{numeric ID extracted from $CLEAN_ARGS}'));

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task with full context using pre-parsed $VECTOR_TASK_ID, verify testable status')
            ->example()
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK', '{task object with title, content, status, parent_id, priority, tags}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status NOT IN ["completed", "tested", "validated", "in_progress"]', [
                Operator::output([
                    '=== TEST VALIDATION BLOCKED ===',
                    'Task #$VECTOR_TASK_ID has status: {$VECTOR_TASK.status}',
                    'Only tasks with status completed/tested/validated can be test-validated.',
                    'Run /task:async $VECTOR_TASK_ID to complete first.',
                ]),
                'ABORT validation',
            ]))
            ->phase(Store::as('IS_SESSION_RECOVERY', 'false'))
            ->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                Operator::note('Task is in_progress - check status_history for session crash indicator'),
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Operator::output([
                        '',
                        '⚠️ SESSION RECOVERY DETECTED',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Continuing test validation without status change.',
                        'NOTE: Previous session vector memory findings should be treated with caution - context may be incomplete.',
                        '',
                    ]),
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== TEST VALIDATION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another active session.',
                        'Wait for completion or use /task:async to take over.',
                    ]),
                    'ABORT test validation',
                ]),
            ]))
            ->phase(Operator::note('CRITICAL: Set TASK_PARENT_ID to the CURRENTLY validated task ID IMMEDIATELY after loading.'))
            ->phase(Store::as('TASK_PARENT_ID', '{$VECTOR_TASK_ID}'))
            ->phase(Operator::note('TASK_PARENT_ID = $VECTOR_TASK_ID (the task we are test-validating NOW). Any fix tasks created will be children of THIS task.'))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                Operator::note('Fetching parent task FOR CONTEXT DISPLAY ONLY. This DOES NOT change TASK_PARENT_ID.'),
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK_CONTEXT', '{parent task for display context only - NOT for parent_id assignment}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 50}'))
            ->phase(Store::as('SUBTASKS', '{list of subtasks}'))
            ->phase(Store::as('TASK_DESCRIPTION', '{$VECTOR_TASK.title + $VECTOR_TASK.content}'))
            ->phase(Store::as('SIMPLE_TEST_VALIDATION', '{true if $VECTOR_TASK.estimate <= 4 AND $VECTOR_TASK.priority !== "critical"}'))
            ->phase(Operator::note('SIMPLE_TEST_VALIDATION = {$SIMPLE_TEST_VALIDATION}'))
            ->phase(Operator::output([
                '=== TASK:TEST-VALIDATE ACTIVATED ===',
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent context: {$PARENT_TASK_CONTEXT.title or "none"}',
                'Subtasks: {$SUBTASKS.count}',
                'Fix tasks parent_id will be: $TASK_PARENT_ID (THIS task)',
            ]));

        // Phase 1: Agent Discovery and Test Validation Scope Preview
        $this->guideline('phase1-validation-preview')
            ->goal('Discover available agents and present test validation scope for approval')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 1: TEST VALIDATION PREVIEW ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents with capabilities'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agent_id: description mapping}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'), 'Get documentation INDEX preview'))
            ->phase(Store::as('DOCS_PREVIEW', 'Documentation files available'))
            ->phase(Operator::output([
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Available agents: {$AVAILABLE_AGENTS.count}',
                'Documentation files: {$DOCS_PREVIEW.count}',
                'Simple validation mode: {$SIMPLE_TEST_VALIDATION}',
                '',
                'Test validation will delegate to agents:',
                '1. VectorMaster - deep memory research for test context',
                '2. DocumentationMaster - testable requirements extraction',
                '3. Selected agents - test discovery + parallel validation (6 aspects, scaled to complexity)',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output([
                    '',
                    '⚠️  APPROVAL REQUIRED',
                    '✅ approved/yes - start test validation | ❌ no/modifications',
                ]),
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['Auto-approval: -y flag detected, skipping confirmation.']),
            ]))
            ->phase('IMMEDIATELY after approval - set task in_progress (test validation IS execution)')
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Test validation started after approval", append_comment: true}'))
            ->phase(Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} started (test validation phase)']));

        // Phase 2: Deep Test Context Gathering via VectorMaster Agent
        $this->guideline('phase2-context-gathering')
            ->goal('Delegate deep test context research to VectorMaster agent')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: DEEP TEST CONTEXT ===',
                'Delegating to VectorMaster for deep memory research...',
            ]))
            ->phase(Operator::if('$IS_SESSION_RECOVERY === true', [
                Operator::note('CAUTION: This is a session recovery. Vector memory findings from the crashed session may be incomplete or stale. Cross-validate memory results with fresh codebase analysis. Do not assume previous session findings are accurate.'),
            ]))
            ->phase('SELECT vector-master from $AVAILABLE_AGENTS')
            ->phase(Store::as('CONTEXT_AGENT', '{vector-master agent_id}'))
            ->phase(TaskTool::agent('{$CONTEXT_AGENT}', 'DEEP MEMORY RESEARCH for test validation of task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Multi-probe search: past test implementations, test patterns, testing best practices, test failures, coverage gaps 2) Search across categories: code-solution, learning, bug-fix 3) Extract test-specific insights: what worked, what failed, patterns used 4) Return: {test_history: [...], test_patterns: [...], past_failures: [...], quality_standards: [...], key_insights: [...]}. Store consolidated test context.'))
            ->phase(Store::as('TEST_MEMORY_CONTEXT', '{VectorMaster agent results}'))
            ->phase(VectorTaskMcp::call('task_list', '{query: "test $TASK_DESCRIPTION", limit: 10}'))
            ->phase(Store::as('RELATED_TEST_TASKS', 'Related test tasks'))
            ->phase(Operator::output([
                'Context gathered via {$CONTEXT_AGENT}:',
                '- Test insights: {$TEST_MEMORY_CONTEXT.key_insights.count}',
                '- Related test tasks: {$RELATED_TEST_TASKS.count}',
            ]));

        // Phase 3: Documentation Requirements Extraction
        $this->guideline('phase3-documentation-extraction')
            ->goal('Extract ALL testable requirements from .docs/ via DocumentationMaster')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: DOCUMENTATION REQUIREMENTS ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', 'Documentation file paths'))
            ->phase(Operator::if('$DOCS_INDEX not empty', [
                TaskTool::agent('documentation-master', 'Extract ALL TESTABLE requirements from documentation files: {$DOCS_INDEX paths}. For each requirement identify: [{requirement_id, description, testable_scenarios: [...], acceptance_criteria, expected_test_type: unit|feature|integration|e2e, priority}]. Focus on BEHAVIOR not implementation. Store to vector memory.'),
                Store::as('DOCUMENTATION_REQUIREMENTS', '{structured testable requirements list}'),
            ]))
            ->phase(Operator::if('$DOCS_INDEX empty', [
                Store::as('DOCUMENTATION_REQUIREMENTS', '[]'),
                Operator::output(['WARNING: No documentation found. Test validation will be limited to existing tests only.']),
            ]))
            ->phase(Operator::output([
                'Testable requirements extracted: {$DOCUMENTATION_REQUIREMENTS.count}',
                '{requirements summary with test types}',
            ]));

        // Phase 4: Test Discovery via Dynamic Agent Selection
        $this->guideline('phase4-test-discovery')
            ->goal('Select best agent from $AVAILABLE_AGENTS and discover all existing tests')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 4: TEST DISCOVERY ===',
            ]))
            ->phase('SELECT AGENT for test discovery from {$AVAILABLE_AGENTS} (prefer explore for codebase scanning)')
            ->phase(Store::as('DISCOVERY_AGENT', '{selected agent_id based on descriptions}'))
            ->phase(TaskTool::agent('{$DISCOVERY_AGENT}', 'DEEP RESEARCH - TEST DISCOVERY for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search vector memory for past test patterns and locations 2) Scan codebase for test directories (tests/, spec/, __tests__) 3) Find ALL related test files: unit, feature, integration, e2e 4) Analyze test structure and coverage 5) Return: [{test_file, test_type, test_classes, test_methods, related_source_files}]. Store findings to vector memory.'))
            ->phase(Store::as('DISCOVERED_TESTS', '{list of test files with metadata}'))
            ->phase(Operator::output([
                'Tests discovered via {$DISCOVERY_AGENT}: {$DISCOVERED_TESTS.count} files',
                '{test files summary by type}',
            ]));

        // Phase 5: Dynamic Agent Selection and Parallel Test Validation
        $this->guideline('phase5-parallel-validation')
            ->goal('Select best agents from $AVAILABLE_AGENTS and launch parallel test validation (scaled to complexity)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 5: PARALLEL TEST VALIDATION ===',
            ]))
            ->phase('AGENT SELECTION: Analyze $AVAILABLE_AGENTS descriptions and select BEST agent for each test validation aspect:')
            ->phase(Operator::if('$SIMPLE_TEST_VALIDATION === false', [
                Operator::do([
                    'ASPECT 1 - REQUIREMENTS COVERAGE: Select agent for requirements-to-test mapping (vector-master for memory, explore for codebase)',
                    'ASPECT 2 - TEST QUALITY: Select agent for code quality analysis (explore for pattern detection)',
                    'ASPECT 3 - WORKFLOW COVERAGE: Select agent for workflow analysis (explore for flow tracing)',
                    'ASPECT 4 - TEST CONSISTENCY: Select agent for consistency analysis (explore for pattern matching)',
                    'ASPECT 5 - TEST ISOLATION: Select agent for isolation analysis (explore for dependency scanning)',
                    'ASPECT 6 - TEST EXECUTION: Select agent capable of running tests (explore with bash access)',
                ]),
                Store::as('SELECTED_AGENTS', '{aspect: agent_id mapping based on $AVAILABLE_AGENTS}'),
            ]))
            ->phase(Operator::if('$SIMPLE_TEST_VALIDATION === true', [
                Operator::output(['Simple validation: reduced agent set (coverage + execution only)']),
                Operator::do([
                    'ASPECT 1 - REQUIREMENTS COVERAGE: Select agent for requirements-to-test mapping',
                    'ASPECT 2 - TEST EXECUTION: Select agent capable of running tests',
                ]),
                Store::as('SELECTED_AGENTS', '{coverage: explore, execution: explore}'),
            ]))
            ->phase(Operator::output([
                'Selected agents for test validation:',
                '{$SELECTED_AGENTS mapping}',
                '',
                'Launching test validation agents in parallel...',
            ]))
            ->phase(Operator::if('$SIMPLE_TEST_VALIDATION === false', [
                'PARALLEL BATCH: Launch selected agents simultaneously with DEEP RESEARCH tasks',
                Operator::do([
                    TaskTool::agent('{$SELECTED_AGENTS.coverage}', 'DEEP RESEARCH - REQUIREMENTS COVERAGE for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search vector memory for past requirement-test mappings 2) Compare {$DOCUMENTATION_REQUIREMENTS} against {$DISCOVERED_TESTS} 3) For each requirement verify test exists 4) Return: [{requirement_id, coverage_status: covered|partial|missing, test_file, test_method, gap_description, memory_refs}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.quality}', 'DEEP RESEARCH - TEST QUALITY for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for test quality standards 2) Analyze {$DISCOVERED_TESTS} for bloat indicators 3) Check: excessive mocking, implementation testing, redundant assertions, copy-paste 4) Return: [{test_file, test_method, bloat_type, severity, suggestion}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.workflow}', 'DEEP RESEARCH - WORKFLOW COVERAGE for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for workflow patterns 2) Verify {$DISCOVERED_TESTS} cover complete user workflows 3) Check: happy path, error paths, edge cases, boundaries 4) Return: [{workflow, coverage_status, missing_scenarios}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.consistency}', 'DEEP RESEARCH - TEST CONSISTENCY for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for project test conventions 2) Check {$DISCOVERED_TESTS} for consistency 3) Verify: naming, structure, assertions, fixtures, setup/teardown 4) Return: [{test_file, inconsistency_type, description, suggestion}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.isolation}', 'DEEP RESEARCH - TEST ISOLATION for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for isolation issues 2) Verify {$DISCOVERED_TESTS} are properly isolated 3) Check: shared state, order dependency, external calls, cleanup 4) Return: [{test_file, isolation_issue, severity, suggestion}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.execution}', 'DEEP RESEARCH - TEST EXECUTION for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for past test failures 2) Run tests related to task 3) Identify flaky tests 4) Return: [{test_file, execution_status: pass|fail|flaky, error_message, execution_time}]. Store findings.'),
                ]),
            ]))
            ->phase(Operator::if('$SIMPLE_TEST_VALIDATION === true', [
                'SIMPLE BATCH: Launch reduced agent set',
                Operator::do([
                    TaskTool::agent('{$SELECTED_AGENTS.coverage}', 'REQUIREMENTS COVERAGE for task #{$VECTOR_TASK_ID}: Compare {$DOCUMENTATION_REQUIREMENTS} against {$DISCOVERED_TESTS}. Return: [{requirement_id, coverage_status, test_file}].'),
                    TaskTool::agent('{$SELECTED_AGENTS.execution}', 'TEST EXECUTION for task #{$VECTOR_TASK_ID}: Run tests. Return: [{test_file, execution_status, error_message}].'),
                ]),
            ]))
            ->phase(Store::as('VALIDATION_BATCH', '{results from all agents}'))
            ->phase(Operator::output([
                'Batch complete: {$SELECTED_AGENTS.count} test validation checks finished',
            ]));

        // Phase 6: Results Aggregation and Analysis
        $this->guideline('phase6-results-aggregation')
            ->goal('Aggregate all test validation results and categorize issues (functional vs cosmetic)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 6: RESULTS AGGREGATION ===',
            ]))
            ->phase('Merge results from all validation agents')
            ->phase(Store::as('ALL_TEST_ISSUES', '{merged issues from all agents}'))
            ->phase('Categorize FUNCTIONAL test issues (require tasks):')
            ->phase(Store::as('MISSING_COVERAGE', '{requirements without tests}'))
            ->phase(Store::as('PARTIAL_COVERAGE', '{requirements with incomplete tests}'))
            ->phase(Store::as('BLOATED_TESTS', '{tests flagged for bloat - logic issues, over-mocking}'))
            ->phase(Store::as('MISSING_WORKFLOWS', '{workflows without end-to-end coverage}'))
            ->phase(Store::as('INCONSISTENT_TESTS', '{tests with consistency issues affecting logic}'))
            ->phase(Store::as('ISOLATION_ISSUES', '{tests with isolation problems}'))
            ->phase(Store::as('FAILING_TESTS', '{tests that fail or are flaky}'))
            ->phase('Categorize COSMETIC test issues (auto-fixable, NO tasks):')
            ->phase(Store::as('COSMETIC_TEST_ISSUES', '{test issues that are purely cosmetic: whitespace in test files, indentation issues, extra/trailing spaces, empty line inconsistencies, comment formatting in tests, test naming style (not logic), docblock formatting - anything NOT affecting test logic or execution}'))
            ->phase(Store::as('FUNCTIONAL_TEST_ISSUES_COUNT', '{$MISSING_COVERAGE.count + $PARTIAL_COVERAGE.count + $BLOATED_TESTS.count + $MISSING_WORKFLOWS.count + $INCONSISTENT_TESTS.count + $ISOLATION_ISSUES.count + $FAILING_TESTS.count}'))
            ->phase(Operator::output([
                'Test validation results:',
                '- Missing coverage: {$MISSING_COVERAGE.count} requirements',
                '- Partial coverage: {$PARTIAL_COVERAGE.count} requirements',
                '- Bloated tests: {$BLOATED_TESTS.count} tests',
                '- Missing workflows: {$MISSING_WORKFLOWS.count} workflows',
                '- Inconsistent tests: {$INCONSISTENT_TESTS.count} tests',
                '- Isolation issues: {$ISOLATION_ISSUES.count} tests',
                '- Failing/flaky tests: {$FAILING_TESTS.count} tests',
                '- Cosmetic test issues (auto-fix): {$COSMETIC_TEST_ISSUES.count}',
                '',
                'Functional test issues total: {$FUNCTIONAL_TEST_ISSUES_COUNT}',
            ]));

        // Phase 6.5: Cosmetic Test Auto-Fix (NO TASKS - immediate parallel agent fix)
        $this->guideline('phase6-5-cosmetic-autofix')
            ->goal('Auto-fix cosmetic test issues via parallel agents WITHOUT creating tasks, then restart test validation if only cosmetic issues exist')
            ->example()
            ->phase(Operator::if('$COSMETIC_TEST_ISSUES.count > 0', [
                Operator::output([
                    '',
                    '=== PHASE 6.5: COSMETIC TEST AUTO-FIX ===',
                    'Found {$COSMETIC_TEST_ISSUES.count} cosmetic test issues (whitespace, formatting)',
                    'Auto-fixing without creating tasks...',
                ]),
                'Group cosmetic test issues by file for parallel processing',
                Store::as('COSMETIC_TEST_FILE_GROUPS', '{group $COSMETIC_TEST_ISSUES by test file path}'),
                'Launch parallel agents to fix cosmetic test issues (max 5 agents)',
                Operator::do([
                    TaskTool::agent('explore',
                        'FIX COSMETIC ISSUES ONLY in test files: {$COSMETIC_TEST_FILE_GROUP_1}. Issues to fix: {issues list}. ONLY fix: whitespace, indentation, trailing spaces, extra empty lines, comment formatting, docblock formatting. DO NOT modify test logic, assertions, or test method structure. Return: {files_fixed: [...], changes_made: [...]}'),
                    TaskTool::agent('explore',
                        'FIX COSMETIC ISSUES ONLY in test files: {$COSMETIC_TEST_FILE_GROUP_2}. Issues to fix: {issues list}. ONLY fix: whitespace, indentation, trailing spaces, extra empty lines, comment formatting, docblock formatting. DO NOT modify test logic, assertions, or test method structure. Return: {files_fixed: [...], changes_made: [...]}'),
                    TaskTool::agent('explore',
                        'FIX COSMETIC ISSUES ONLY in test files: {$COSMETIC_TEST_FILE_GROUP_3}. Issues to fix: {issues list}. ONLY fix: whitespace, indentation, trailing spaces, extra empty lines, comment formatting, docblock formatting. DO NOT modify test logic, assertions, or test method structure. Return: {files_fixed: [...], changes_made: [...]}'),
                ]),
                Store::as('COSMETIC_TEST_FIX_RESULTS', '{results from cosmetic test fix agents}'),
                Operator::output([
                    'Cosmetic test fixes applied: {$COSMETIC_TEST_FIX_RESULTS.total_fixed} issues in {$COSMETIC_TEST_FIX_RESULTS.files_count} files',
                ]),
            ]))
            ->phase(Operator::note('DECISION POINT: If ONLY cosmetic test issues existed, restart validation to verify fixes'))
            ->phase(Operator::if('$COSMETIC_TEST_ISSUES.count > 0 AND $FUNCTIONAL_TEST_ISSUES_COUNT === 0', [
                Operator::note('All test issues were cosmetic - restart validation from Phase 0 to verify fixes'),
                Store::as('VALIDATION_ITERATION', '{$VALIDATION_ITERATION + 1 or 1 if not set}'),
                Operator::if('$VALIDATION_ITERATION <= 3', [
                    VectorTaskMcp::call('task_update',
                        '{task_id: $VECTOR_TASK_ID, comment: "Cosmetic test auto-fix iteration {$VALIDATION_ITERATION}: fixed {$COSMETIC_TEST_ISSUES.count} issues. Restarting test validation.", append_comment: true}'),
                    Operator::output([
                        '',
                        '🔄 All test issues were cosmetic and have been auto-fixed.',
                        'Restarting test validation from Phase 0 (iteration {$VALIDATION_ITERATION}/3)...',
                        '',
                    ]),
                    'RESTART test validation from Phase 0',
                    'GOTO: phase0-task-loading',
                ]),
                Operator::if('$VALIDATION_ITERATION > 3', [
                    Operator::output([
                        '',
                        '⚠️ Max validation iterations (3) reached.',
                        'Proceeding to completion with remaining cosmetic issues.',
                    ]),
                    'Continue to Phase 8 (skip Phase 7 - no functional test issues)',
                ]),
            ]))
            ->phase(Operator::if('$COSMETIC_TEST_ISSUES.count > 0 AND $FUNCTIONAL_TEST_ISSUES_COUNT > 0', [
                Operator::output([
                    '',
                    '✅ Cosmetic test issues auto-fixed.',
                    '📋 Proceeding to Phase 7 for {$FUNCTIONAL_TEST_ISSUES_COUNT} functional test issues...',
                ]),
                'Continue to Phase 7 with functional test issues only',
            ]))
            ->phase(Operator::if('$COSMETIC_TEST_ISSUES.count === 0', [
                Operator::output(['No cosmetic test issues found. Proceeding to Phase 7...']),
            ]));

        // Phase 7: Task Creation for FUNCTIONAL Test Gaps Only (Consolidated 5-8h Tasks)
        $this->guideline('phase7-task-creation')
            ->goal('Create consolidated tasks (5-8h each) for FUNCTIONAL test gaps with comprehensive context (cosmetic issues already auto-fixed)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 7: TASK CREATION (CONSOLIDATED) ===',
            ]))
            ->phase(Operator::note('CRITICAL VERIFICATION: Confirm TASK_PARENT_ID before creating any tasks'))
            ->phase(Operator::verify([
                '$TASK_PARENT_ID === $VECTOR_TASK_ID',
                'TASK_PARENT_ID is the ID of the task we are test-validating (NOT its parent)',
            ]))
            ->phase(Operator::output([
                'Fix tasks will have parent_id: $TASK_PARENT_ID (Task #{$VECTOR_TASK_ID})',
            ]))
            ->phase('Check existing tasks to avoid duplicates')
            ->phase(VectorTaskMcp::call('task_list', '{query: "test $TASK_DESCRIPTION", limit: 20}'))
            ->phase(Store::as('EXISTING_TEST_TASKS', 'Existing test tasks'))
            ->phase(Operator::note('Phase 7 processes ONLY functional test issues. Cosmetic issues were auto-fixed in Phase 6.5'))
            ->phase(Operator::if('$FUNCTIONAL_TEST_ISSUES_COUNT === 0', [
                Operator::output(['No functional test issues to create tasks for. Proceeding to Phase 8...']),
                'SKIP to Phase 8',
            ]))
            ->phase('CONSOLIDATION STRATEGY: Group FUNCTIONAL test issues into 5-8 hour task batches')
            ->phase(Operator::do([
                'Calculate total estimate for FUNCTIONAL test issues only:',
                '- Missing coverage: ~2h per requirement (tests + assertions)',
                '- Failing tests: ~1h per test (debug + fix)',
                '- Bloated tests: ~1.5h per test (refactor + verify)',
                '- Missing workflows: ~3h per workflow (e2e test suite)',
                '- Isolation issues: ~1h per test (refactor + verify)',
                '(Cosmetic issues NOT included - already auto-fixed)',
                Store::as('TOTAL_ESTIMATE', '{sum of FUNCTIONAL test issue estimates in hours}'),
            ]))
            ->phase(Operator::if('$TOTAL_ESTIMATE <= 8', [
                'ALL issues fit into ONE consolidated task (5-8h range)',
                Operator::if('$ALL_TEST_ISSUES.count > 0 AND NOT exists similar in $EXISTING_TEST_TASKS', [
                    VectorTaskMcp::call('task_create', '{
                        title: "Test fixes: task #{$VECTOR_TASK_ID}",
                        content: "Consolidated test validation findings for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}.\\n\\nTotal estimate: {$TOTAL_ESTIMATE}h\\n\\n## Missing Coverage ({$MISSING_COVERAGE.count})\\n{FOR each req: - {req.description} | Type: {req.expected_test_type} | File: {req.related_file}:{req.line} | Scenarios: {req.testable_scenarios}}\\n\\n## Failing Tests ({$FAILING_TESTS.count})\\n{FOR each test: - {test.test_file}:{test.test_method} | Error: {test.error_message} | Status: {test.execution_status}}\\n\\n## Bloated Tests ({$BLOATED_TESTS.count})\\n{FOR each test: - {test.test_file}:{test.test_method} | Bloat: {test.bloat_type} | Suggestion: {test.suggestion}}\\n\\n## Missing Workflows ({$MISSING_WORKFLOWS.count})\\n{FOR each wf: - {wf.workflow} | Missing: {wf.missing_scenarios}}\\n\\n## Isolation Issues ({$ISOLATION_ISSUES.count})\\n{FOR each test: - {test.test_file} | Issue: {test.isolation_issue} | Fix: {test.suggestion}}\\n\\n## Context References\\n- Parent task: #{$VECTOR_TASK_ID}\\n- Memory IDs: {$TEST_MEMORY_CONTEXT.memory_ids}\\n- Related tasks: {$RELATED_TEST_TASKS.ids}\\n- Documentation: {$DOCS_INDEX.paths}",
                        priority: "high",
                        estimate: $TOTAL_ESTIMATE,
                        tags: ["test-validation", "consolidated"],
                        parent_id: $TASK_PARENT_ID
                    }'),
                    Store::as('CREATED_TASKS[]', '{task_id}'),
                    Operator::output(['Created consolidated task: Test fixes ({$TOTAL_ESTIMATE}h, {$ALL_TEST_ISSUES.count} issues)']),
                ]),
            ]))
            ->phase(Operator::if('$TOTAL_ESTIMATE > 8', [
                'Split into multiple 5-8h task batches',
                Store::as('BATCH_SIZE', '6'),
                Store::as('NUM_BATCHES', '{ceil($TOTAL_ESTIMATE / 6)}'),
                'Group issues by priority and type into batches of ~6h each',
                Operator::forEach('batch_index in range(1, $NUM_BATCHES)', [
                    Store::as('BATCH_ISSUES', '{slice of issues for this batch, ~6h worth}'),
                    Store::as('BATCH_ESTIMATE', '{sum of batch issue estimates}'),
                    Operator::if('NOT exists similar in $EXISTING_TEST_TASKS', [
                        VectorTaskMcp::call('task_create', '{
                            title: "Test fixes batch {batch_index}/{$NUM_BATCHES}: task #{$VECTOR_TASK_ID}",
                            content: "Test validation batch {batch_index} of {$NUM_BATCHES} for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}.\\n\\nBatch estimate: {$BATCH_ESTIMATE}h\\n\\n## Issues in this batch\\n{FOR each issue in $BATCH_ISSUES:\\n### {issue.type}: {issue.title}\\n- File: {issue.file}:{issue.line}\\n- Description: {issue.description}\\n- Severity: {issue.severity}\\n- Suggestion: {issue.suggestion}\\n- Related memory: {issue.memory_refs}\\n}\\n\\n## Full Context References\\n- Parent task: #{$VECTOR_TASK_ID}\\n- Memory IDs: {$TEST_MEMORY_CONTEXT.memory_ids}\\n- Related tasks: {$RELATED_TEST_TASKS.ids}\\n- Documentation: {$DOCS_INDEX.paths}\\n- Total batches: {$NUM_BATCHES} ({$TOTAL_ESTIMATE}h total)",
                            priority: "{batch_index === 1 ? high : medium}",
                            estimate: $BATCH_ESTIMATE,
                            tags: ["test-validation", "batch-{batch_index}"],
                            parent_id: $TASK_PARENT_ID
                        }'),
                        Store::as('CREATED_TASKS[]', '{task_id}'),
                        Operator::output(['Created batch {batch_index}/{$NUM_BATCHES}: {$BATCH_ESTIMATE}h']),
                    ]),
                ]),
            ]))
            ->phase(Operator::output([
                'Tasks created: {$CREATED_TASKS.count} (total estimate: {$TOTAL_ESTIMATE}h)',
            ]));

        // Task Consolidation Rules
        $this->rule('task-size-5-8h')->high()
            ->text('Each created task MUST have estimate between 5-8 hours. Never create tasks < 5h (consolidate) or > 8h (split).')
            ->why('Optimal task size for focused work sessions. Too small = context switching overhead. Too large = hard to track progress.')
            ->onViolation('Merge small issues into consolidated task OR split large task into 5-8h batches.');

        $this->rule('task-comprehensive-context')->critical()
            ->text('Each task MUST include: all file:line references, memory IDs, related task IDs, documentation paths, detailed issue descriptions with suggestions.')
            ->why('Enables full context restoration without re-exploration. Saves agent time on task pickup.')
            ->onViolation('Add missing context references before creating task.');

        // Phase 8: Test Validation Completion
        $this->guideline('phase8-completion')
            ->goal('Complete test validation, update task status, store summary to memory')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 8: TEST VALIDATION COMPLETE ===',
            ]))
            ->phase(Store::as('COVERAGE_RATE', '{covered_requirements / total_requirements * 100}%'))
            ->phase(Store::as('TEST_HEALTH_SCORE', '{100 - (bloat_count + isolation_count + failing_count) / total_tests * 100}%'))
            ->phase(Store::as('VALIDATION_STATUS', Operator::if('$MISSING_COVERAGE.count === 0 AND $FAILING_TESTS.count === 0', 'PASSED', 'NEEDS_WORK')))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Test validation of task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nStatus: {$VALIDATION_STATUS}\\nCoverage rate: {$COVERAGE_RATE}\\nTest health: {$TEST_HEALTH_SCORE}\\n\\nMissing coverage: {$MISSING_COVERAGE.count}\\nFailing tests: {$FAILING_TESTS.count}\\nBloated tests: {$BLOATED_TESTS.count}\\nTasks created: {$CREATED_TASKS.count}\\n\\nKey findings: {summary}", category: "code-solution", tags: ["test-validation", "audit", "task:test-validate"]}'))
            ->phase(Operator::if('$VALIDATION_STATUS === "PASSED" AND $CREATED_TASKS.count === 0', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "tested", comment: "Test validation PASSED. All requirements covered, all tests passing, no critical issues.", append_comment: true}'),
                Operator::output(['✅ Task #{$VECTOR_TASK_ID} marked as TESTED']),
            ]))
            ->phase(Operator::if('$CREATED_TASKS.count > 0', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Test validation found issues. Coverage: {$COVERAGE_RATE}, Health: {$TEST_HEALTH_SCORE}. Created {$CREATED_TASKS.count} fix tasks. Returning to pending - fix tasks must be completed before re-testing.", append_comment: true}'),
                Operator::output(['⏳ Task #{$VECTOR_TASK_ID} returned to PENDING ({$CREATED_TASKS.count} fix tasks required before re-testing)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== TEST VALIDATION REPORT ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VALIDATION_STATUS}',
                '',
                '| Metric | Value |',
                '|--------|-------|',
                '| Requirements coverage | {$COVERAGE_RATE} |',
                '| Test health score | {$TEST_HEALTH_SCORE} |',
                '| Total tests | {$DISCOVERED_TESTS.count} |',
                '| Passing tests | {passing_count} |',
                '| Failing/flaky tests | {$FAILING_TESTS.count} |',
                '',
                '| Issue Type | Count |',
                '|------------|-------|',
                '| Missing coverage | {$MISSING_COVERAGE.count} |',
                '| Partial coverage | {$PARTIAL_COVERAGE.count} |',
                '| Bloated tests | {$BLOATED_TESTS.count} |',
                '| Missing workflows | {$MISSING_WORKFLOWS.count} |',
                '| Isolation issues | {$ISOLATION_ISSUES.count} |',
                '| Cosmetic (auto-fixed) | {$COSMETIC_TEST_ISSUES.count} |',
                '',
                'Tasks created: {$CREATED_TASKS.count}',
                '{IF $COSMETIC_TEST_ISSUES.count > 0: "✅ Cosmetic test issues auto-fixed without tasks"}',
                '{IF $CREATED_TASKS.count > 0: "Follow-up tasks: {$CREATED_TASKS}"}',
                '',
                'Test validation stored to vector memory.',
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Graceful error handling for test validation process')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'Abort validation',
            ])
            ->phase()->if('vector task not in testable status', [
                'Report: "Vector task #{id} status is {status}, not completed/tested/validated"',
                'Suggest: Run /task:async #{id} first',
                'Abort validation',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use /do:test-validate for text-based validation"',
                'Abort command',
            ])
            ->phase()->if('no documentation found', [
                'Warn: "No documentation in .docs/ for this task"',
                'Continue with test-only validation (existing tests analysis)',
                'Note: "Cannot verify requirements coverage without documentation"',
            ])
            ->phase()->if('no tests found', [
                'Report: "No tests found for task #{$VECTOR_TASK_ID}"',
                'Create task: "Write initial tests for {$TASK_DESCRIPTION}"',
                'Continue with documentation requirements analysis',
            ])
            ->phase()->if('test execution fails', [
                'Log: "Test execution failed: {error}"',
                'Mark tests as "execution_unknown"',
                'Continue with static analysis',
            ])
            ->phase()->if('agent validation fails', [
                'Log: "Validation agent {N} failed: {error}"',
                'Continue with remaining agents',
                'Report partial validation in summary',
            ])
            ->phase()->if('task creation fails', [
                'Log: "Failed to create task: {error}"',
                'Store issue details to vector memory for manual review',
                'Continue with remaining tasks',
            ]);

        // Test Quality Criteria
        $this->guideline('test-quality-criteria')
            ->text('Criteria for evaluating test quality (bloat detection)')
            ->example()
            ->phase('BLOAT INDICATORS (flag for refactoring):')
            ->do([
                'Excessive mocking (>3 mocks per test)',
                'Testing private methods directly',
                'Testing getters/setters without logic',
                'Copy-paste test code (>80% similarity)',
                'Single assertion tests without context',
                'Testing framework internals',
                'Hard-coded magic values without explanation',
                'Test method >50 lines',
                'Setup >30 lines',
            ])
            ->phase('QUALITY INDICATORS (good tests):')
            ->do([
                'Tests behavior, not implementation',
                'Readable test names (given_when_then)',
                'Single responsibility per test',
                'Proper use of fixtures/factories',
                'Edge cases covered',
                'Error paths tested',
                'Fast execution (<100ms per test)',
                'No external dependencies without mocks',
            ]);

        // Constraints and Validation
        $this->guideline('constraints')
            ->text('Test validation constraints and limits')
            ->example()
            ->phase('Max 6 parallel validation agents per batch')
            ->phase('Max 30 tasks created per validation run')
            ->phase('Test execution timeout: 5 minutes total')
            ->phase('Bloat threshold: >50% bloated = critical warning')
            ->phase(Operator::verify([
                'vector_task_loaded = true',
                'testable_status_verified = true',
                'parallel_agents_used = true',
                'documentation_checked = true',
                'tests_executed = true',
                'results_stored_to_memory = true',
            ]));

        // Examples
        $this->guideline('example-vector-task')
            ->scenario('Test validate completed vector task')
            ->example()
            ->phase('input', '"task 15" or "#15" where task #15 is completed')
            ->phase('load', 'task_get(15) → title, content, status: completed')
            ->phase('flow', 'Task Loading → Context → Docs → Test Discovery → Parallel Validation (6 agents) → Aggregate → Create Tasks → Complete')
            ->phase('result', 'Test validation PASSED/NEEDS_WORK, coverage %, N tasks created');

        $this->guideline('example-with-fixes')
            ->scenario('Test validation finds issues')
            ->example()
            ->phase('input', '"#28" where task #28 has status: completed')
            ->phase('validation', 'Found: 2 missing coverage, 1 bloated test, 1 failing')
            ->phase('tasks', 'Created 1 consolidated fix task (6h estimate)')
            ->phase('result', 'Task #28 status → pending, 1 fix task created as child');

        $this->guideline('example-rerun')
            ->scenario('Re-run test validation (idempotent)')
            ->example()
            ->phase('input', '"task 15" (already test-validated before)')
            ->phase('behavior', 'Skips existing tasks, only creates NEW issues found')
            ->phase('result', 'Same/updated validation report, no duplicate tasks');

        // When to use task:test-validate vs do:test-validate
        $this->guideline('test-validate-vs-do-test-validate')
            ->text('When to use /task:test-validate vs /do:test-validate')
            ->example()
            ->phase('USE /task:test-validate', 'Validate tests for specific vector task by ID (15, #15, task 15). Best for: systematic task-based workflow, hierarchical task management, fix task creation as children.')
            ->phase('USE /do:test-validate', 'Validate tests by text description ("test-validate user authentication"). Best for: ad-hoc test validation, exploratory validation, no existing vector task.');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | Parallel: agent batch indicators | Tables: coverage metrics + issue counts | Coverage % | Health score | Created tasks listed | 📋 task ID references');
    }
}
